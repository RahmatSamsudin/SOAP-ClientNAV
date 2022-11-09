<?php

namespace App\Http\Controllers;

use App\Helpers\NTLMSoapClient;
use App\Helpers\NTLMStream;
use App\Mail\NAVSend;
use App\Models\ExportNAV;
use App\Models\DataPOS;
use App\Models\LogExportNav;
use App\Models\DataTransaction;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use Exception;
use SoapFault;

class SalesOrderController extends Controller
{
    private $date = '';
    private $start = '';
    private $end = '';
    private $time = '';
    private $error = [];
    private $skipped = [];
    private $processed = [];
    public $running_number;


    public function index()
    {
        set_time_limit(0);
        date_default_timezone_set('Asia/Jakarta');
        $today = Carbon::now();
        $this->setVar('date',  $today->format('Y-m-d'));
        $this->setVar('start', $this->getVar('date') . ' 06:00:00');
        $this->setVar('end', $this->getVar('date') . ' 20:00:00');
        #$this->setVar('start', $this->getVar('date'). ' 23:00:00');
        #$this->setVar('end', $today->addDays(1)->format('Y-m-d'). ' 07:00:00');
        #$head = $this->_proccessHeader(collect(DB::select("SELECT * FROM export_nav JOIN store ON store.store_id=export_nav.store WHERE document_number IN ('20220630051','20220630061','20220630057','20220630058') AND export_status=0 AND export_nav=1 ORDER BY sales_date,store ASC")));
        $head = $this->_proccessHeader(collect(DB::select("SELECT * FROM export_nav JOIN store ON store.store_id=export_nav.store WHERE document_number IN (
            '20220630051',
            '20220630061'
            '20220630057',
            '20220630058'
        )
        AND export_status=0 AND export_nav=1 ORDER BY sales_date,store ASC")));
        /*
        $head = $this->_proccessHeader(collect(DB::select('
            SELECT * FROM export_nav JOIN store ON store.store_id=export_nav.store WHERE sales_date < "'.Carbon::now()->subDays(14).'" AND export_status=0 AND export_nav=1 ORDER BY sales_date,store ASC'
        )));
        */
        if (count($this->skipped) > 0) {
            $this->skipped = collect($this->skipped);
            $this->_proccessHeader($this->skipped);
        }
        if (count($head) > 0) {
            Mail::to(['rahmat@sushitei.co.id', 'benardi@sushitei.co.id', 'augus@sushitei.co.id'])
                ->send(new NAVSend($head));
        }



        echo '<br/>OKE';
    }

    public function setVar($name, $value)
    {
        $this->{$name} = $value;
    }

    public function getVar($name)
    {
        return $this->{$name};
    }

    private function _proccessHeader(object $headers)
    {
        $head = [];

        foreach ($headers as $k => $header) {
            $currentTime = date("Y-m-d H:i:s");
            if ($currentTime >= $this->getVar('start') && $currentTime <= $this->getVar('end')) {
                // Processing Sales Header
                $this->setVar('time', $currentTime);
                $success = false;
                $this->running_number = intval(($k+1).'0001');
                $head[$k]['custno'] = $header->nav_code;
                $head[$k]['orderdate'] = $header->sales_date;
                $head[$k]['extdocno'] = $header->document_number;
                $head[$k]['shop_name'] = $header->store_name;
                $head[$k]['export_id'] = $header->export_id;
                $head[$k]['shop_id'] = $header->store_id;
                $head[$k]['location_id'] = $header->location_id;
                $head[$k]['is_success'] = 0;
                $head[$k]['start'] = $currentTime;
                $head[$k]['end'] = $currentTime;
                #$head[$i]['data_cps'] = DataTransaction::daily($header->document_number);
                #$head[$i]['data_pos'] = DataPOS::transaction($header->document_number, $header->location_id)->get();
                try {
                    $success = $this->_sendDataHeader($head[$k]);
                } catch (SoapFault $fault) {
                    $head[$k]['error'][] = 'Sales Header No. ' . $header->document_number . ' Error : ' . $fault->faultstring;
                } catch (Exception $ex) {
                    $head[$k]['error'][] = $ex->getMessage();
                }


                // Processing Sales Line
                if ($success) {
                    if ($header->need_cp) {
                        $head[$k]['cps'] = $this->_proccessCPS($head[$k]);
                        #dd($this->_proccessCPS($head[$i]), $head[$i]);
                    } else {
                        $head[$k]['cps']['quantity'] = 0;
                        $head[$k]['cps']['total'] = 0;
                        $head[$k]['cps']['line'] = 0;
                    }
                    if(!array_key_exists('error', $head[$k]['cps'])){
                        $head[$k]['pos'] = $this->_proccessPOS($head[$k]);
                    }
                }

                // Count Processing Data + Error Data
                if ($success && !array_key_exists('error', $head[$k]['cps']) && !array_key_exists('error', $head[$k]['pos'])) {
                    $head[$k]['is_success'] = 1;
                    ExportNAV::where('export_id', $header->export_id)->update(['export_status' => 1]);
                    LogExportNav::create([
                        'export_id' => $header->export_id,
                        'message' => 'Success',
                        'quantity' => $head[$k]['cps']['quantity'] + $head[$k]['pos']['quantity'],
                        'total' => $head[$k]['cps']['total'] + $head[$k]['pos']['total'],
                        'created_at' => $currentTime
                    ]);
                } else {
                    ExportNAV::where('export_id', $header->export_id)->update(['last_update' => date("Y-m-d H:i:s")]);
                }
                $head[$k]['end'] = date("Y-m-d H:i:s");
            } else {

                echo '<br/>outside time ' . $currentTime;
                echo '<br/>start at ' . $this->getVar('start');
                echo '<br/>end at ' . $this->getVar('end');
                break;
            }
        }

        return $head;
    }

    private function _proccessCPS(array $head)
    {
        $error = 0;
        $return['quantity'] = 0;
        $return['total'] = 0;
        $return['processed'] = 0;
        #dd(DataTransaction::daily($head['extdocno']), $head);

        try {
            foreach (DataTransaction::daily($head['extdocno']) as $line) {
                if(!$error){
                    $data = [
                        'extdocno' => $head['extdocno'],
                        'loccode' => $head['custno'],
                        'salestype' => $line->col2,
                        'itemno' => $line->item_code,
                        'qty' => $line->sumqty,
                        'unitprice' =>  $line->price,
                        'totalprice' =>  $line->sumqtyprice,
                        'postdocumentid' =>  (int) $this->running_number,
                        'desc' =>  $line->item_name,
                    ];
                    $this->_sendDataLines($data);
                    $return['quantity'] = $return['quantity'] + $line->sumqty;
                    $return['total'] = $return['total'] + $line->sumqtyprice;
                    $return['processed']++;
                    $this->running_number++;
                }
            }
        } catch (\Throwable $ex) {
            $error = 1;
            $message = 'CPS ' . $ex->getMessage();
            $return['error'] = $message;
            LogExportNav::create([
                'export_id' => $head['export_id'],
                'message' => $message,
                'quantity' => !empty($return['quantity']) ? $return['quantity'] : 0,
                'total' => !empty($return['total']) ? $return['total'] : 0,
                'created_at' => $this->getVar('time')
            ]);
        }
        return $return;
    }

    private function _proccessPOS(array $head)
    {
        $return['quantity'] = 0;
        $return['total'] = 0;
        $return['processed'] = 0;
        #dd(DataPOS::transaction($head['extdocno'], $head['location_id'])->get(), $head);


        try {
            foreach (DataPOS::transaction($head['extdocno'], $head['location_id'])->get() as $sale) {
                $this->_sendDataLines([
                    'extdocno' => $head['extdocno'],
                    'loccode' => $head['custno'],
                    'salestype' => $sale['sales_type_id'],
                    'itemno' => $sale['item_code'],
                    'qty' => $sale['sales_qty'],
                    'unitprice' =>  $sale['sales_price'],
                    'totalprice' =>  $sale['sales_price'] * $sale['sales_qty'],
                    'postdocumentid' =>  $this->running_number,
                    'desc' => $sale['item_name'],
                ]);

                if ($sale['sales_type_id'] != 21) {
                    $return['quantity'] = $return['quantity'] + $sale['sales_qty'];
                    $return['total'] = $return['total'] + ($sale['sales_qty'] * $sale['sales_price']);
                }
                $return['processed']++;
                $this->running_number++;
            }
        } catch (\Throwable $ex) {
            $message = 'POS ' . $ex->getMessage();
            $return['error'] = $message;
            LogExportNav::create([
                'export_id' => $head['export_id'],
                'message' => $message,
                'quantity' => !empty($return['quantity']) ? $return['quantity'] : 0,
                'total' => !empty($return['total']) ? $return['total'] : 0,
                'created_at' => $this->getVar('time')
            ]);
            #throw new \Exception($message);
        }

        return $return;
    }

    private function _sendDataHeader(array $params)
    {
        //dd($params);
        //To Communicate With the Web Service Using NTLM You Must Override the HTTP with NTLMSteam to allow Windows Authentication to work.
        //See https://thomas.rabaix.net/blog/2008/03/using-soap-php-with-ntlm-authentication for full Explanation
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'App\Helpers\NTLMStream') or die("Failed to register protocol");

        // //Ensure you can get a list of services by entering the Services URL in a Web Browser - No Point Continuing until you verify that The Web Services is running.
        // $servicesURL = 'http://172.16.6.206:7047/DynamicsNAV90/WS/Services';

        // Initialize Soap Client URL
        $baseURL = env('NAV_BASE_URL', true);

        //Define Company Name - This value will need to be urlencoded
        $CompanyName = env("NAV_COMPANY_NAME", false);

        //>>>>>>>>>>>>>>>>>Item Query>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
        $pageURL = $baseURL . rawurlencode($CompanyName) . '/Codeunit/NAVSync';

        $codeunitURL = $baseURL . rawurlencode($CompanyName) . '/Codeunit/NAVSync';

        // Initialize Page Soap Client
        $codeunit = new NTLMSoapClient($codeunitURL);

        $result = $codeunit->APIImportSOHeader($params);


        return $result;
        // Put back the HTTP protocal to esure we do not affect other operations.
        stream_wrapper_restore('http');
    }

    private function _sendDataLines(array $params)
    {
        //dd($params);
        //To Communicate With the Web Service Using NTLM You Must Override the HTTP with NTLMSteam to allow Windows Authentication to work.
        //See https://thomas.rabaix.net/blog/2008/03/using-soap-php-with-ntlm-authentication for full Explanation
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'App\Helpers\NTLMStream') or die("Failed to register protocol");

        // //Ensure you can get a list of services by entering the Services URL in a Web Browser - No Point Continuing until you verify that The Web Services is running.
        // $servicesURL = 'http://172.16.6.206:7047/DynamicsNAV90/WS/Services';

        // Initialize Soap Client URL
        $baseURL = env('NAV_BASE_URL', false);

        //Define Company Name - This value will need to be urlencoded
        $CompanyName = env("NAV_COMPANY_NAME", false);

        //>>>>>>>>>>>>>>>>>Item Query>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>
        $pageURL = $baseURL . rawurlencode($CompanyName) . '/Codeunit/NAVSync';

        $codeunitURL = $baseURL . rawurlencode($CompanyName) . '/Codeunit/NAVSync';

        // Initialize Page Soap Client
        $codeunit = new NTLMSoapClient($codeunitURL);

        $result = $codeunit->APIImportSOLine($params);

        return $result;
        // Put back the HTTP protocal to esure we do not affect other operations.
        stream_wrapper_restore('http');
    }
}
