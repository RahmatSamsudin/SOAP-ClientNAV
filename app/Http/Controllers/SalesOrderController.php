<?php

namespace App\Http\Controllers;

use App\Helpers\NTLMSoapClient;
use App\Helpers\NTLMStream;
use App\Models\ExportNAV;
use App\Models\DataPOS;
use App\Models\ItemExcluded;
use App\Models\DataTransaction;
use Exception;
use Illuminate\Http\Request;
use SoapFault;

class SalesOrderController extends Controller
{
    public function index(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');
        $start = date('d-m-Y H:i:s');
        $errorLog = [];
        $countProcess = 0;
        $countError = 0;
        $countHead = 0;
        // set_time_limit(500);
        $SalesHeader = ExportNAV::readyExport();
            //$codeunitURL = env('NAV_BASE_URL') . rawurlencode(env("NAV_COMPANY_NAME")) . '/Codeunit/NAVSync';
            //exit(dd($codeunitURL));
        // return $SalesHeader;
        $lines = [];
        foreach ($SalesHeader as $a) {
            // Processing Sales Header
            $header = true;
            $line = true;
            $head[$countHead] = [
                'custno' => $a->stores['nav_code'],
                'orderdate' => $a->sales_date,
                'extdocno' => $a->document_number
            ];
            //exit(var_dump($header['orderdate']));
            try {
                $header = $this->_sendDataHeader($head[$countHead]);
            } catch (SoapFault $fault) {
                $header = false;
                $head[$countHead]['error'][] = 'Sales Header No. ' . $a->document_number . ' Error : ' . $fault->faultstring;
            } catch (Exception $ex) {
                $head[$countHead]['error'][] = $ex->getMessage();
            };

            // Processing Sales Line
            if ($header) {
                set_time_limit(0);
                $head[$countHead]['line'] = 0;
                $head[$countHead]['processed'] = 0;
                // Processing POS DATA
                try {
                    $salesLines = DataPOS::transaction($a->store, $a->sales_date, $a->stores['location_id'])->get();
                    // exit(json_encode($salesLines, JSON_PRETTY_PRINT));
                    $head[$countHead]['line'] = count($salesLines);
                    if ($line) {
                        for ($i = 0; $i < $head[$countHead]['line']; $i++) {
                            if ($line) {
                                $this->_sendDataLines([
                                    'extdocno' => $a->document_number,
                                    'loccode' => $a->stores['nav_code'],
                                    'salestype' => $salesLines[$i]['sales_type_id'],
                                    'itemno' => $salesLines[$i]['item_code'],
                                    'qty' => $salesLines[$i]['sales_qty'],
                                    'unitprice' =>  $salesLines[$i]['sales_price'],
                                    'totalprice' =>  $salesLines[$i]['sales_price']*$salesLines[$i]['sales_qty'],
                                    'postdocumentid' =>  $salesLines[$i]['pos_data_id'],
                                    'desc' =>   $salesLines[$i]['item_name'],
                                ]);
                                $head[$countHead]['processed']++;
                            }
                        }
                    }
                } catch (SoapFault $fault) {
                    $line = false;
                    $head[$countHead]['error'][] = "Header: {$a->document_number}   POS Line No. ".($i+1)." of {$head[$countHead]['line']}  id ({$salesLines[$i]['pos_data_id']}) Error : {$fault->faultstring}";
                } catch (Exception $ex) {
                    $line = false;
                    $head[$countHead]['error'][] = 'POS '.$ex->getMessage();
                }
                // Processing CPS DATA
                try {
                    $cpsLines = DataTransaction::daily($a->stores['store_id'], $head[$countHead]['orderdate']);
                    if ($line) {
                        $i = 0;
                        $head[$countHead]['processed'] = 0;
                        foreach($cpsLines as $cpsLine){
                            if ($line) {
                                $this->_sendDataLines([
                                    'extdocno' => $a->document_number,
                                    'loccode' => $a->stores['nav_code'],
                                    'salestype' => $cpsLine->col2,
                                    'itemno' => $cpsLine->item_code,
                                    'qty' => $cpsLine->sumqty,
                                    'unitprice' =>  $cpsLine->price,
                                    'totalprice' =>  $cpsLine->sumqtyprice,
                                    'postdocumentid' =>  $i,
                                    'desc' =>  $cpsLine->item_name,
                                ]);
                                $head[$countHead]['processed']++;
                            }
                            $i++;
                        }
                    }
                    $head[$countHead]['line'] = $head[$countHead]['line']+$i;
                } catch (SoapFault $fault) {
                    $line = false;
                    $head[$countHead]['error'][] = "Header: {$a->document_number}   CPS Line No. {$i} of ".count($cpsLines)." Error : {$fault->faultstring}";
                } catch (Exception $ex) {
                    $line = false;
                    $head[$countHead]['error'][] = 'CPS '.$ex->getMessage();
                }
            }

            // Count Processing Data + Error Data
            if ($header && $line) {
                ExportNAV::where('document_number', $a->document_number)->update(['export_status' => 1]);
            } else {
                $countError++;
            }

            $countHead++;
        }

        echo '<pre>'.json_encode([
            'Error Data' => $countError,
            'Process Data' => $countProcess,
            'header' => $head,
        ], JSON_PRETTY_PRINT).'</pre>';
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
