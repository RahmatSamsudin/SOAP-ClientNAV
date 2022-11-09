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

    public function index()
    {
        set_time_limit(0);
        date_default_timezone_set('Asia/Jakarta');
        $today = Carbon::now();
        $this->setVar('date',  $today->format('Y-m-d'));
        #$this->setVar('start', $this->getVar('date'). ' 06:00:00');
        #$this->setVar('end', $this->getVar('date'). ' 20:50:00');
        $this->setVar('start', $this->getVar('date'). ' 23:00:00');
        $this->setVar('end', $today->addDays(1)->format('Y-m-d'). ' 07:00:00');

        // Processing Sales Header
        /*
        echo '<pre>';
        var_dump(ExportNAV::readyExport());
        echo '</pre>';
        */
        $head = $this->_proccessHeader(ExportNAV::readyExport());
        if(count($head) > 0){
            Mail::to(['rahmat@sushitei.co.id','benardi@sushitei.co.id', 'augus@sushitei.co.id'])
            ->send(new NAVSend($head));
        }



        echo '<pre>'.json_encode($head, JSON_PRETTY_PRINT).'</pre>';


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
        #echo 'processing header';
        $head = [];
        $i = 0;

        #exit(dd($headers->get()));
        foreach ($headers->get() as $header) {

           # echo '<br/> loop no '.$i;
            $currentTime = date("Y-m-d H:i:s");
            if($currentTime >= $this->getVar('start') && $currentTime <= $this->getVar('end')){
                // Processing Sales Header
                $this->setVar('time', $currentTime);
                $success = true;
                $head[$i] = [
                    'custno' => $header->stores['nav_code'],
                    'orderdate' => $header->sales_date,
                    'extdocno' => $header->document_number,
                ];


                try {
                    $success = $this->_sendDataHeader($head[$i]);

                } catch (SoapFault $fault) {
                    $success = false;
                    $head[$i]['error'][] = 'Sales Header No. ' . $header->document_number . ' Error : ' . $fault->faultstring;
                } catch (Exception $ex) {
                    $success = false;
                    $head[$i]['error'][] = $ex->getMessage();
                };
                $head[$i]['shop_name'] = $header->stores['store_name'];
                $head[$i]['export_id'] = $header->export_id;
                $head[$i]['shop_id'] = $header->stores['store_id'];
                $head[$i]['location_id'] = $header->stores['location_id'];
                $head[$i]['is_success'] = 0;
                $head[$i]['start'] = $currentTime;

                // Processing Sales Line
                if ($success) {
                    if($header->stores['need_cp']){
                        $head[$i]['cps'] = $this->_proccessCPS($head[$i]);
                    }else{
                        $head[$i]['cps']['quantity'] = 0;
                        $head[$i]['cps']['total'] = 0;
                        $head[$i]['cps']['line'] = 0;
                    }
                    
                    if(empty($head[$i]['cps']['error'])){
                        $head[$i]['pos'] = $this->_proccessPOS($head[$i], true);
                    }
                }

                // Count Processing Data + Error Data
                if ($success && empty($head[$i]['cps']['error']) && empty($head[$i]['pos']['error'])) {
                    $head[$i]['is_success'] = 1;
                    ExportNAV::where('document_number', $header->document_number)->update(['export_status' => 1]);
                    LogExportNav::create([
                        'export_id' => $header->export_id,
                        'message' => 'Success',
                        'quantity' => $head[$i]['cps']['quantity']+$head[$i]['pos']['quantity'],
                        'total' => $head[$i]['cps']['total']+$head[$i]['pos']['total'],
                        'created_at' => $currentTime
                    ]);
                } else {
                    ExportNAV::where('document_number', $header->document_number)->update(['last_update' => date("Y-m-d H:i:s")]);
                }
                $head[$i]['end'] = date("Y-m-d H:i:s");
            }else{

                echo '<br/>outside time '.$currentTime;
                echo '<br/>start at '.$this->getVar('start');
                echo '<br/>end at '.$this->getVar('end');
                break 1;
            }
            $i++;

        }

        return $head;
    }

    private function _proccessCPS(array $head)
    {
        $line = true;
        $return['quantity'] = 0;
        $return['total'] = 0;
       # echo '<br/><pre>'.print_r($head).'<br />';
        try {
            $cpsLines = DataTransaction::daily($head['shop_id'], $head['extdocno']);
            if ($line) {
                $i = 0;
                $return['processed'] = 0;
                foreach($cpsLines as $cpsLine){
                    if ($line) {
                        $this->_sendDataLines([
                            'extdocno' => $head['extdocno'],
                            'loccode' => $head['custno'],
                            'salestype' => $cpsLine->col2,
                            'itemno' => $cpsLine->item_code,
                            'qty' => $cpsLine->sumqty,
                            'unitprice' =>  $cpsLine->price,
                            'totalprice' =>  $cpsLine->sumqtyprice,
                            'postdocumentid' =>  $i,
                            'desc' =>  $cpsLine->item_name,
                        ]);
                        $return['processed']++;
                        $return['quantity'] = $return['quantity']+$cpsLine->sumqty;
                        $return['total'] = $return['total']+$cpsLine->sumqtyprice;
                    }
                    $i++;
                }
            }
            $return['line'] = $i;

        } catch (SoapFault $fault) {
            $line = false;
            $message = "Header: {$head['extdocno']}   CPS Line No. {$i} of ".count($cpsLines)." Error : {$fault->faultstring}";
            $return['error'][] = $message;
            LogExportNav::create([
                'export_id' => $head['export_id'],
                'message' => $message,
                'quantity' => !empty($return['quantity']) ? $return['quantity'] : 0,
                'total' => !empty($return['total']) ? $return['total'] :0,
                'created_at' => $this->getVar('time')
            ]);
        } catch (Exception $ex) {
            $line = false;
            $message = 'CPS: '.$ex->getMessage();
            $return['error'][] = $message;
            LogExportNav::create([
                'export_id' => $head['export_id'],
                'message' => $message,
                'quantity' => !empty($return['quantity']) ? $return['quantity'] : 0,
                'total' => !empty($return['total']) ? $return['total'] :0,
                'created_at' => $this->getVar('time')
            ]);
        }

        return $return;
    }

    private function _proccessPOS(array $head, $line)
    {
        $return['quantity'] = 0;
        $return['total'] = 0;
        #echo '<br/><pre>'.print_r($head).'<br />';
        // Processing POS DATA
        try {
            $sales = DataPOS::transaction($head['shop_id'], $head['orderdate'], $head['location_id'])->get();
            // exit(json_encode($salesLines, JSON_PRETTY_PRINT));
            $return['line'] = count($sales);
            $return['processed'] = 0;
            $i = 0;

            foreach($sales as $sale){
                if ($line) {
                    $this->_sendDataLines([
                        'extdocno' => $head['extdocno'],
                        'loccode' => $head['custno'],
                        'salestype' => $sale['sales_type_id'],
                        'itemno' => $sale['item_code'],
                        'qty' => $sale['sales_qty'],
                        'unitprice' =>  $sale['sales_price'],
                        'totalprice' =>  $sale['sales_price']*$sale['sales_qty'],
                        'postdocumentid' =>  $sale['pos_data_id'],
                        'desc' => $sale['item_name'],
                    ]);
                    $return['processed']++;
                    if($sale['sales_type_id'] != 21){
                        $return['quantity'] = $return['quantity']+$sale['sales_qty'];
                        $return['total'] = $return['total']+($sale['sales_qty']*$sale['sales_price']);
                    }
                }
                $i++;
            }

        } catch (SoapFault $fault) {
            $line = false;
            $message = "Header: {$head['extdocno']}   POS Line No. ".($i+1)." of {$return['line']}  id ({$sale['pos_data_id']}) Error : {$fault->faultstring}";
            $return['error'][] = $message;
            LogExportNav::create([
                'export_id' => $head['export_id'],
                'message' => $message,
                'quantity' => !empty($return['quantity']) ? $return['quantity'] : 0,
                'total' => !empty($return['total']) ? $return['total'] :0,
                'created_at' => $this->getVar('time')
            ]);
        } catch (Exception $ex) {
            $line = false;
            $message = 'POS '.$ex->getMessage();
            $return['error'][] = $message;
            LogExportNav::create([
                'export_id' => $head['export_id'],
                'message' => $message,
                'quantity' => !empty($return['quantity']) ? $return['quantity'] : 0,
                'total' => !empty($return['total']) ? $return['total'] :0,
                'created_at' => $this->getVar('time')
            ]);
        }
        // Processing CPS DATA
        return $return;
    }

    private function _sendDataHeader(array $params)
    {
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
