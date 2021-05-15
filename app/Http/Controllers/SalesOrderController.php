<?php

namespace App\Http\Controllers;

use App\Helpers\NTLMSoapClient;
use App\Helpers\NTLMStream;
use App\Models\SalesHeader;
use App\Models\SalesLine;
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
        // set_time_limit(500);
        $SalesHeader = SalesHeader::with(['salesLines' => function ($query) {
            $query->orderBy('item_no', 'asc');
        }])
            ->where('sync', false)
            ->orderBy('orderdate', 'asc')
            ->orderBy('custno', 'asc')
            ->limit(200)
            ->get();

        // return $SalesHeader;
        foreach ($SalesHeader as $a) {
            // Processing Sales Header
            $header = true;
            $line = true;
            $header = [
                'custno' => $a->custno,
                'orderdate' => $a->orderdate,
                'extdocno' => $a->extdocno
            ];

            try {
                $header = $this->_sendDataHeader($header);
            } catch (SoapFault $fault) {
                $header = false;
                array_push($errorLog, 'Sales Header No. ' . $a->extdocno . ' Error : ' . $fault->faultstring);
            } catch (Exception $ex) {
                array_push($errorLog, $ex->getMessage());
            };

            // Processing Sales Line
            if ($header) {
                set_time_limit(500);
                try {
                    $salesLines = $a->salesLines;
                    $clines = count($salesLines);
                    if ($line) {
                        for ($i = 0; $i < $clines; $i++) {
                            if ($line) {
                                $lines = [
                                    'extdocno' => $salesLines[$i]['document_no'],
                                    'loccode' => $salesLines[$i]['location_code'],
                                    'salestype' => $salesLines[$i]['sales_type'],
                                    'itemno' => $salesLines[$i]['item_no'],
                                    'qty' => $salesLines[$i]['quantity'],
                                    'unitprice' =>  $salesLines[$i]['price'],
                                    'totalprice' =>  $salesLines[$i]['total_price'],
                                    'postdocumentid' =>  $salesLines[$i]['id'],
                                    'desc' =>  $salesLines[$i]['description'],
                                ];
                                $lines = $this->_sendDataLines($lines);
                            }
                        }
                    }
                } catch (SoapFault $fault) {
                    $line = false;
                    array_push($errorLog, 'Sales Line No. ' . $a->extdocno . ' id (' . $salesLines[$i]['postdocumentid'] . ') Error : ' . $fault->faultstring);
                } catch (Exception $ex) {
                    $line = false;
                    array_push($errorLog, $ex->getMessage());
                };
            }

            // Count Processing Data + Error Data
            $countProcess++;
            if ($header && $line) {
                SalesHeader::where('extdocno', $a->extdocno)
                    ->update(['sync' => true]);
            } else {
                $countError++;
            }
        }

        echo json_encode([
            'Start Time' => $start,
            'End Date' => date('d-m-Y H:i:s'),
            'Error Data' => $countError,
            'Process Data' => $countProcess,
            'messageError' => $errorLog
        ]);
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
