<?php

namespace App\Http\Controllers;

use App\Helpers\NTLMSoapClient;
use App\Helpers\NTLMStream;
use App\Mail\NAVSend;
use App\Models\ExportNAV;
use App\Models\ExportLine;
use App\Models\DataPOS;
use App\Models\Store;
use App\Models\LogExportNav;
use App\Models\DataTransaction;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use Exception;
use SoapFault;

class MonthlySalesController extends Controller
{
    private $date = '';
    private $start = '';
    private $end = '';
    private $time = '';
    private $error = [];
    private $skipped = [];
    private $processed = [];
    public $endOfMonth = null;
    public $startOfMonth = null;
    public $running_number;
    public $is_console;

    public function __construct()
    {
        $this->endOfMonth = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
        $this->startOfMonth = Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d');
    }


    /**
     * Retrieves and sends the NAV export data via email.
     *
     * @param int $console Flag to indicate if the function is being run in a console.
     * @param int $waste Flag to indicate if the function should include waste data.
     * @throws Some_Exception_Class Exception thrown if there is an error sending the email.
     * @return void
     */
    public function index($console = 0, $waste = 0)
    {
        set_time_limit(0);

        date_default_timezone_set('Asia/Jakarta');

        if ($console) {
            $this->is_console = true;
        }

        $today = Carbon::now();
        $date = $today->format('Y-m-d');
        $header = [];
        $i = 0;
        $this->setVar('start', $date . ' 07:00:00');
        $this->setVar('end', $today->addDays(1)->format('Y-m-d') . ' 06:00:00');

        $stores = Store::where('export_nav', 1)
            ->whereIn('location_id', [1, 12, 17, 18, 21, 22])
            ->where('store_status', 1)
            ->get();
        foreach ($stores as $store) {
            $export_navs = ExportNAV::where('is_waste', intval($waste))
                ->where('store', $store->store_id)
                ->whereDate('sales_date', '>=', $this->startOfMonth)
                ->whereDate('sales_date', '<=', $this->endOfMonth)
                ->orderBy('store', 'asc')
                ->get();
            $has_exported = collect($export_navs)->where('export_status', 1)->count();
            $header[$i] = $store;
            if ($has_exported == 0) {
                $header[$i]['export_id'] = collect($export_navs)->pluck('export_id')->all();
            }else{
                unset($header[$i]);
            }
            
            $i++;
        }


        $head = $this->proccessHeader(Collect($header));
        $email = new NAVSend($head, $waste);

        if (count($head) > 0) {
            $recipients = ['rahmat@sushitei.co.id', 'benardi@sushitei.co.id'];
            Mail::to($recipients)->send($email);
        }
    }

    public function setVar($name, $value)
    {
        $this->{$name} = $value;
    }

    public function getVar($name)
    {
        return $this->{$name};
    }

    /**
     * Processes the header data.
     *
     * @param object $headers The header data to be processed.
     * @throws Exception If an error occurs during processing.
     * @return array The processed header data.
     */
    private function proccessHeader(object $headers)
    {
        $head = [];
        foreach ($headers as $i => $header) {
            $currentTime = date("Y-m-d H:i:s");
            if ($this->isWithinTimeRange($currentTime)) {
                $this->setVar('time', $currentTime);
                $head[$i] = $this->prepareData($header, $currentTime);

                try {
                    $success = $this->sendDataHeader($head[$i]);
                    if ($success) {
                        $head[$i]['line'] = $this->proccessLine($head[$i]);
                        if (empty($head[$i]['line']['error'])) {
                            $head[$i]['is_success'] = 1;
                            if(empty($header->export_id)){
                                continue;
                            }
                            ExportNAV::whereIn('export_id', $header->export_id)->update(['export_status' => 1, 'last_update' => date("Y-m-d H:i:s")]);
                            foreach($header->export_id as $id){
                                LogExportNav::create([
                                    'export_id' => $id,
                                    'message' => 'Success',
                                    'quantity' => 0,
                                    'total' => 0,
                                    'created_at' => $currentTime
                                ]);
                            }
                        } else {
                            ExportNAV::whereIn('export_id', $header->export_id)->update(['last_update' => date("Y-m-d H:i:s")]);
                        }
                    }
                } catch (Exception $ex) {
                    
                    $head[$i]['error'][] = $ex->getMessage();
                }


                $head[$i]['end'] = date("Y-m-d H:i:s");
                } else {
                    $separator = $this->is_console ? "<br/>" : "\r\n";
                    $output = '';
                    $output .= 'Running Schedule for ' . date('Y-m-d', strtotime($this->getVar('start'))) . $separator;
                    if (count($head) > 0) {
                        $output .= "Completed at {$currentTime}{$separator}";
                    } else {
                        $output .= "Failed:{$separator}";
                        $output .= "Allowed time for sending is between{$separator}";
                        $output .= $this->getVar('start') . " and " . $this->getVar('end') . $separator;
                    }

                    echo $output;
                    break;
            }
        }

        return $head;
    }

    /**
     * Check if the given time is within a specified time range.
     *
     * @param string $currentTime The current time to check.
     * @return bool Returns true if the current time is within the specified range, false otherwise.
     */
    private function isWithinTimeRange(string $currentTime): bool
    {
        $start = strtotime($this->getVar('start'));
        $end = strtotime($this->getVar('end'));
        $current = strtotime($currentTime);

        return $current >= $start && $current <= $end;
    }

    /**
     * Prepare the data for processing.
     *
     * @param object $header The header object.
     * @param mixed $currentTime The current time.
     * @throws Some_Exception_Class Description of exception.
     * @return array The prepared data.
     */
    private function prepareData(object $header, $currentTime)
    {
        $head = [
            'custno' => $header->nav_code,
            'orderdate' => $this->endOfMonth,
            'extdocno' => date("Ym", strtotime($this->endOfMonth)) . $header->nav_code,
            'shop_name' => $header->store_name,
            'export_id' => $header->export_id,
            'shop_id' => $header->store_id,
            'location_id' => $header->location_id,
            'is_success' => 0,
            'start' => $currentTime,
            'end' => $currentTime,
        ];


        return $head;
    }



    /**
     * Process each line in the given array.
     *
     * @param array $head the array containing the line data
     * @throws Exception if an error occurs during processing
     * @return array the processed line data
     */
    private function proccessLine(array $head)
    {
        # As long as $line is true, the loop will be running
        $line = true;
        $return = [
            'quantity' => 0,
            'total' => 0,
            'processed' => 0,
            'error' => []
        ];
        $location = $head['custno'];
        $lineNo = 1;
        $lastPostDocID = '';

        $catchDataLines = [];
        $preDocid = Carbon::parse($head['orderdate'])->format('Ym') . $head['shop_id'];
        try {

            if($head['export_id']){
                
                $export_lines = ExportLine::selectRaw('itemno,store_id,`desc`,SUM(qty) AS qty,unitprice, SUM(qty) * unitprice as totalprice')
                ->whereIn('export_id', $head['export_id'])
                ->where('itemno', '<>', '')
                ->orderBy('itemno', 'asc')
                ->groupBy(['itemno', 'desc', 'unitprice'])
                ->get();
                #dd($export_lines);
                foreach ($export_lines as $baris) {
                    if (!$line) break 1;
                    $bnum = sprintf("%04d", $lineNo);
                    $postdocumentid = $preDocid . $bnum;
                    $currentLine = [
                        'postdocumentid' => $postdocumentid,
                        'extdocno' => $head['extdocno'],
                        'loccode' => $location,
                        'salestype' => 2,
                        'itemno' => trim($baris->itemno),
                        'qty' => $baris->qty,
                        'unitprice' => $baris->unitprice,
                        'totalprice' => $baris->totalprice,
                        'desc' => trim($baris->desc)
                    ];
                    $lastPostDocID = $currentLine;

                    // array_push($catchDataLines, $currentLine);
                    $this->sendDataLines($currentLine);
                    $return['quantity'] += $currentLine['qty'];
                    $return['total'] += $currentLine['totalprice'];
                    $lineNo++;
                }
            }
            
// dd($catchDataLines);
            #dd($export_lines,$lineNo);
        } catch (Exception $ex) {
            
            $line = false;
            $message = "Line Error: {$postdocumentid} - " . $ex->getMessage();
            $return['error'][] = $message;

            // LogExportNav::create([
            //     'export_id' => $baris['export_id'],
            //     'message' => $message,
            //     'quantity' => $return['quantity'],
            //     'total' => $return['total'],
            //     'created_at' => $this->getVar('time')
            // ]);
        }

        return $return;
    }

    private function sendDataHeader(array $params)
    {
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'App\Helpers\NTLMStream');

        $baseURL = env('NAV_BASE_URL', true);
        $companyName = env("NAV_COMPANY_NAME", false);

        $pageURL = $baseURL . rawurlencode($companyName) . '/Codeunit/NAVSync';
        $codeunitURL = $baseURL . rawurlencode($companyName) . '/Codeunit/NAVSync';

        $codeunit = new NTLMSoapClient($codeunitURL);

        $result = $codeunit->APIImportSOHeader($params);

        stream_wrapper_restore('http');

        return $result;
    }

    private function sendDataLines(array $params)
    {
        stream_wrapper_unregister('http');
        stream_wrapper_register('http', 'App\Helpers\NTLMStream') or die("Failed to register protocol");

        $baseURL = env('NAV_BASE_URL', false);
        $companyName = env("NAV_COMPANY_NAME", false);

        $pageURL = $baseURL . rawurlencode($companyName) . '/Codeunit/NAVSync';
        $codeunitURL = $baseURL . rawurlencode($companyName) . '/Codeunit/NAVSync';

        $codeunit = new NTLMSoapClient($codeunitURL);

        $result = $codeunit->APIImportSOLine($params);

        stream_wrapper_restore('http');

        return $result;
    }
}
