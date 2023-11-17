<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NAVSend extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $waste;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Array $data, $waste = 0)
    {
        //
        $this->data = $data;
        $this->waste = $waste;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        if($this->waste){
            return $this->from('it.helpdesk@sushitei.co.id', 'CPS to NAV 2023 Waste Report')
                ->view('emails.waste');
        }else{
            return $this->from('it.helpdesk@sushitei.co.id', 'CPS to NAV 2023 Daily Report')
                ->view('emails.daily');
        }
    }
}
