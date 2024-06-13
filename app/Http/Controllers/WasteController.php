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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

use Exception;
use SoapFault;

class WasteController extends Controller
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


    /**
     * Retrieves and sends the NAV export data via email.
     *
     * @param int $console Flag to indicate if the function is being run in a console.
     * @param int $waste Flag to indicate if the function should include waste data.
     * @throws Some_Exception_Class Exception thrown if there is an error sending the email.
     * @return void
     */
    public function index($console = 0, $waste = 1)
    {
        set_time_limit(0);
        
        date_default_timezone_set('Asia/Jakarta');

        if ($console) {
            $this->is_console = true;
        }

        $today = Carbon::now();
        $date = $today->format('Y-m-d');
        $this->setVar('start', $date . ' 06:00:00');
        $this->setVar('end', $today->addDays(1)->format('Y-m-d') . ' 22:59:00');

        $query = ExportNAV::with('stores')
            ->where('export_status', 0)
            ->whereHas('stores', function ($query) {
                return $query->where('export_nav', '=', 1)->whereIn('location_id', [1, 12]);
            })
            ->whereDate('sales_date', '>=', '2023-01-01')
            ->whereDate('sales_date', '<=', Carbon::now()->subDays(7))
            ->orderBy('sales_date', 'asc')
            ->orderBy('store', 'asc');

        $isWaste = $waste ? '1' : '0';
        $head = $this->proccessHeader(Collect($query->where('is_waste', '=', $isWaste)->get()));
        $email = new NAVSend($head, $waste);

        if (count($head) > 0) {
            $recipients = ['rahmat@sushitei.co.id', 'benardi@sushitei.co.id', 'augus@sushitei.co.id', 'isa.jkt@sushitei.co.id'];
            Mail::to($recipients)->send($email);
        }else{
            $separator = $this->is_console ? "\r\n" : "<br/>";
            $output = '';
            $output .= 'No data to proccess ' . $separator;
            
            echo $output;
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
                        $head[$i]['line'] = $header->is_waste ? $this->proccessWaste($head[$i]) : $this->proccessLine($head[$i]);
                        if (empty($head[$i]['line']['error'])) {
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
            'custno' => $header->stores->nav_code,
            'orderdate' => $header->sales_date,
            'extdocno' => $header->document_number,
            'shop_name' => $header->stores->store_name,
            'export_id' => $header->export_id,
            'shop_id' => $header->store,
            'location_id' => $header->stores->location_id,
            'is_success' => 0,
            'start' => $currentTime,
            'end' => $currentTime,
        ];

        $checkExportLine = ExportLine::where('export_id', $header->export_id)->count();

        if ($header->is_waste) {
            if ($checkExportLine == 0) {
                $wasteData = DataTransaction::waste($header->store, $header->sales_date);
                $exportLines = [];
                foreach ($wasteData as $line) {
                    $exportLines[] = [
                        'export_id' => $header->export_id,
                        'store_id' => $header->store,
                        'busidate' => "{$header->sales_date}",
                        'extdocno' => "{$header->document_number}",
                        'loccode' => $header->stores->nav_code,
                        'salestype' => 99,
                        'itemno' => "{$line->item_code}",
                        'qty' => $line->sumqty,
                        'unitprice' => $line->price,
                        'totalprice' => $line->sumqtyprice,
                        'desc' => "{$line->item_name}",
                        'is_cps' => 1
                    ];
                }
                ExportLine::insert($exportLines);
            }
        } else {
            if ($checkExportLine == 0) {
                $dailyData = DataTransaction::daily($header->store, $header->sales_date);
                $posData = DataPOS::transaction($header->store, $header->sales_date, $header->stores->location_id)->get();

                $exportLines = [];

                foreach ($dailyData as $line) {
                    $exportLines[] = [
                        'export_id' => $header->export_id,
                        'store_id' => $header->store,
                        'busidate' => "{$header->sales_date}",
                        'extdocno' => "{$header->document_number}",
                        'loccode' => $header->stores->nav_code,
                        'salestype' => $line->col2,
                        'itemno' => "{$line->item_code}",
                        'qty' => $line->sumqty,
                        'unitprice' => $line->price,
                        'totalprice' => $line->sumqtyprice,
                        'desc' => "{$line->item_name}",
                        'is_cps' => 1
                    ];
                }

                foreach ($posData as $line) {
                    $exportLines[] = [
                        'export_id' => $header->export_id,
                        'store_id' => $header->store,
                        'busidate' => "{$header->sales_date}",
                        'extdocno' => "{$header->document_number}",
                        'loccode' => $header->stores->nav_code,
                        'salestype' => $line['sales_type_id'],
                        'itemno' => "{$line['item_code']}",
                        'qty' => $line['sales_qty'],
                        'unitprice' => $line['sales_price'],
                        'totalprice' => $line['sales_price'] * $line['sales_qty'],
                        'desc' => "{$line['item_name']}",
                        'is_cps' => 0
                    ];
                }

                ExportLine::insert($exportLines);
            }
        }


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

                $this->sendDataLines($currentLine);

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
            $message = "Line Error: {$postdocumentid} - " . $ex->getMessage();
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

    /**
     * Processes the waste data.
     *
     * @param array $head The head data.
     * @throws \Throwable If an error occurs during the process.
     * @return array The processed waste data.
     */
    private function proccessWaste(array $head): array
    {
        $return = [
            'quantity' => 0,
            'total' => 0,
            'processed' => 0,
            'error' => []
        ];

        $now = Carbon::now()->format('Y-m-d');
        $hashed = Hash::make($now.'|bot', [
            'rounds' => 10,
        ]);
        

        try {
            $exportLines = ExportLine::select('busidate AS date', 'itemno AS material', 'desc AS description', 'qty AS quantity')
                ->addSelect(DB::raw("'portion' AS uom"))
                ->addSelect(DB::raw("'{$head['custno']}' AS location"))
                ->addSelect(DB::raw("'' as notes"))
                ->addSelect('totalprice')
                ->where('export_id', $head['export_id'])
                ->where('salestype', 99)
                ->orderBy('itemno', 'asc')
                ->get();
            foreach($exportLines as $line){
                $return['quantity'] += $line['quantity'];
                $return['total'] += $line['totalprice'];
                $return['processed']++;
            }
            
            $response = Http::withHeaders(['Key-Access' => $hashed])
                ->post('http://172.16.6.217:12210/$/waste', ['line' => $exportLines->toArray()]);

            if ($response->failed()) {
                $response->throw();
            }
            
        } catch (\Throwable $ex) {
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


}
