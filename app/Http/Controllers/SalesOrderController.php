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
    public $is_console;


    public function index($console = 0)
    {
        set_time_limit(0);
        date_default_timezone_set('Asia/Jakarta');

        if ($console) {
            $this->is_console = true;
        }

        $today = Carbon::now();
        $date = $today->format('Y-m-d');
        $start = $date . ' 22:00:00';
        $end = $today->addDays(1)->format('Y-m-d') . ' 06:00:00';

        $query = ExportNAV::with('stores')
            ->where('export_status', 0)
            ->whereHas('stores', function ($query) {
                return $query->where('export_nav', '=', 1)->whereIn('location_id', [1, 12, 17, 18, 21, 22]);
            })
            ->whereDate('sales_date', '>=', '2023-01-01')
            ->whereDate('sales_date', '<=', Carbon::now()->subDays(7))
            ->orderBy('sales_date', 'asc')
            ->orderBy('store', 'asc');

        $head = $this->_proccessHeader(Collect($query->get()));

        if (count($this->skipped) > 0) {
            $this->skipped = collect($this->skipped);
            $this->_proccessHeader($this->skipped);
        }

        if (count($head) > 0) {
            $recipients = ['rahmat@sushitei.co.id', 'benardi@sushitei.co.id', 'augus@sushitei.co.id', 'isa3.jkt@sushitei.co.id'];
            Mail::to($recipients)->send(new NAVSend($head));
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

    private function _proccessHeader(object $headers)
    {
        $head = [];
        foreach ($headers as $i => $header) {
            $currentTime = date("Y-m-d H:i:s");
            // Processing Sales Header
            if ($currentTime >= $this->getVar('start') && $currentTime <= $this->getVar('end')) {
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
                $checkExportLine = ExportLine::where('export_id', $header->export_id)->get();
                if ($checkExportLine->count() == 0) {
                    foreach (DataTransaction::daily($header->store, $header->sales_date) as $line) {
                        ExportLine::create([
                            'export_id' => $header->export_id,
                            'store_id' => $header->store,
                            'busidate' => $header->sales_date,
                            'extdocno' => $header->document_number,
                            'loccode' => $header->stores->nav_code,
                            'salestype' => $line->col2,
                            'itemno' => $line->item_code,
                            'qty' => $line->sumqty,
                            'unitprice' =>  $line->price,
                            'totalprice' =>  $line->sumqtyprice,
                            'desc' =>  $line->item_name,
                            'is_cps' => 1
                        ]);
                    }
                    foreach (DataPOS::transaction($header->store, $header->sales_date, $header->stores->location_id)->get() as $line) {
                        ExportLine::create([
                            'export_id' => $header->export_id,
                            'store_id' => $header->store,
                            'busidate' => $header->sales_date,
                            'extdocno' => $header->document_number,
                            'loccode' => $header->stores->nav_code,
                            'salestype' => $line['sales_type_id'],
                            'itemno' => $line['item_code'],
                            'qty' => $line['sales_qty'],
                            'unitprice' =>  $line['sales_price'],
                            'totalprice' =>  $line['sales_price'] * $line['sales_qty'],
                            'desc' => $line['item_name']
                        ]);
                    }
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
            } else {
                if (!$this->is_console) {
                    $separator = "\r\n";
                } else {
                    $separator = "<br/>";
                }
                $output = '';
                $output .= 'Running Schedule for  ' . date('Y-m-d', strtotime($this->getVar('start'))) . $separator;
                if (count($head) > 0) {
                    $output .= "Completed at {$currentTime}{$separator}";
                } else {
                    $output .= "Failed:{$separator}";
                    $output .= "Allowed time for sending is between{$separator}";
                    $output .= $this->getVar('start') . " and " . $this->getVar('end') . $separator;
                }

                echo $output;


                break 1;
            }
        }

        return $head;
    }

    private function _proccessLine(array $head)
    {
        // As long as $line is true, the loop will be running
        $line = true;
        $return = [
            'quantity' => 0,
            'total' => 0,
            'processed' => 0,
            'error' => []
        ];

        $location = sprintf("%03d", $head['shop_id']);

        try {
            $export_lines = ExportLine::where('export_id', $head['export_id'])
                ->orderBy('itemno', 'asc')
                ->get();

            foreach ($export_lines as $baris) {
                # stop the loop when $line is false
                if (!$line) {
                    break 1;
                }
                $bdate = date("Ymd", strtotime($baris->busidate));
                $bnum = sprintf("%04d", $return['processed']);
                $postdocumentid = (!empty($baris->sent_document_id) ? $baris->sent_document_id : "{$bdate}{$location}{$bnum}");

                $currentLine = [
                    'postdocumentid' => $postdocumentid,
                    'extdocno' => $baris->extdocno,
                    'loccode' => $baris->loccode,
                    'salestype' => $baris->salestype,
                    'itemno' => $baris->itemno,
                    'qty' => $baris->qty,
                    'unitprice' => $baris->unitprice,
                    'totalprice' => $baris->totalprice,
                    'desc' => $baris->desc
                ];

                if ($baris->busidate < '2023-08-01') {
                    $currentLine['postdocumentid'] = $baris->id;
                }

                $this->_sendDataLines($currentLine);

                $baris->sent_document_id = $currentLine['postdocumentid'];
                $baris->save();

                if ($baris->salestype != 21) {
                    $return['quantity'] += $baris->qty;
                    $return['total'] += $baris->totalprice;
                }

                $return['processed']++;
            }
        } catch (Exception $ex) {
            $line = false;
            $message = 'Line Error: ' . $ex->getMessage();
            $return['error'][] = $message;

            LogExportNav::create([
                'export_id' => $head['export_id'],
                'message' => $message,
                'quantity' => $return['quantity'],
                'total' => $return['total'],
                'created_at' => $this->getVar('time')
            ]);
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
