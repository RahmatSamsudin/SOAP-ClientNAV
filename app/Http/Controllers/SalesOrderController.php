<?php

namespace App\Http\Controllers;

use App\Helpers\NTLMSoapClient;
use App\Helpers\NTLMStream;
use App\Mail\NAVSend;
use App\Models\ExportNAV;
use App\Models\ExportLine;
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

        foreach ($headers as $i => $header) {
            #dd($header);
            #dd($header->stores->store_name);
            $currentTime = date("Y-m-d H:i:s");
            // Processing Sales Header
            $this->setVar('time', $currentTime);
            $success = false;
            $head[$i]['custno'] = $header->stores->nav_code;
            $head[$i]['orderdate'] = $header->sales_date;
            $head[$i]['extdocno'] = $header->document_number;
            $head[$i]['shop_name'] = $header->stores->store_name;
            $head[$i]['export_id'] = $header->export_id;
            $head[$i]['shop_id'] = $header->store;
            $head[$i]['location_id'] = $header->stores->location_id;
            $head[$i]['is_success'] = 0;
            $head[$i]['start'] = $currentTime;
            $head[$i]['end'] = $currentTime;
            ExportLine::where('export_id', $header->export_id)->delete();
            foreach(DataTransaction::daily($header->document_number) as $line){
                ExportLine::create([
                    'export_id' => $header->export_id,
                    'store_id' => $header->store,
                    'extdocno' => $header->document_number,
                    'loccode' => $header->stores->nav_code,
                    'salestype' => $line->col2,
                    'itemno' => $line->item_code,
                    'qty' => $line->sumqty,
                    'unitprice' =>  $line->price,
                    'totalprice' =>  $line->sumqtyprice,
                    'desc' =>  $line->item_name,
                ]);
            }
            foreach(DataPOS::transaction($header->document_number, $header->stores->location_id)->get() as $line){
                ExportLine::create([
                    'export_id' => $header->export_id,
                    'store_id' => $header->store,
                    'extdocno' => $header->document_number,
                    'loccode' => $header->stores->nav_code,
                    'salestype' => $line['sales_type_id'],
                    'itemno' => $line['item_code'],
                    'qty' => $line['sales_qty'],
                    'unitprice' =>  $line['sales_price'],
                    'totalprice' =>  $line['sales_price'] * $line['sales_qty'],
                    'desc' => $line['item_name'],
                ]);
            }

            try {
                $success = $this->_sendDataHeader($head[$i]);
            } catch (Exception $ex) {
                $head[$i]['error'][] = $ex->getMessage();
            }


            // Processing Sales Line
            if ($success) {
                $head[$i]['line'] = $this->_proccessLine($head[$i]);
            }

            // Count Processing Data + Error Data
            if ($success && empty($head[$i]['line']['error'])) {
                $head[$i]['is_success'] = 1;
                ExportNAV::where('export_id', $header->export_id)->update(['export_status' => 1]);
                LogExportNav::create([
                    'export_id' => $header->export_id,
                    'message' => 'Success',
                    'quantity' => $head[$i]['line']['quantity'],
                    'total' => $head[$i]['line']['total'],
                    'created_at' => $currentTime
                ]);
            } else {
                ExportNAV::where('export_id', $header->export_id)->update(['last_update' => date("Y-m-d H:i:s")]);
            }
            $head[$i]['end'] = date("Y-m-d H:i:s");
        }

        return $head;
    }

    private function _proccessLine(array $head)
    {

        $line = true;
        $return['quantity'] = 0;
        $return['total'] = 0;
        try {
            $return['processed'] = 0;
            foreach (ExportLine::select('id as postdocumentid', 'extdocno', 'loccode', 'salestype', 'itemno', 'qty', 'unitprice', 'totalprice', 'desc')->where('export_id', $head['export_id'])->get() as $line) {
                if ($line) {
                    $this->_sendDataLines($line->toArray());
                    $return['processed']++;
                    $return['quantity'] = $return['quantity'] + $line->sumqty;
                    $return['total'] = $return['total'] + $line->sumqtyprice;
                }
            }
        } catch (Exception $ex) {
            $line = false;
            $message = 'Line Error: ' . $ex->getMessage();
            $return['error'][] = $message;
            LogExportNav::create([
                'export_id' => $head['export_id'],
                'message' => $message,
                'quantity' => $return['quantity'] ,
                'total' => $return['total'],
                'created_at' => $this->getVar('time')
            ]);
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
