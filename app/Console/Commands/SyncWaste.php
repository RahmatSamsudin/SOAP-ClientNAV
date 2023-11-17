<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\SalesOrderController as SW;

class SyncWaste extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cps:syncwaste';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start Sync CPS Waste';

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
        $soc = new SW();
        return $soc->index(1,1);
    }
}
