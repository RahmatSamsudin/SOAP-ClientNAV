<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\MonthlySalesController as MSC;

class SyncMonthly extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cps:monthly';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monthly Send to NAV';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $msc = new MSC();
        return $msc->index(1);
    }
}
