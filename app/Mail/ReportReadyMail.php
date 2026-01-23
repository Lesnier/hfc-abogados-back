<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $url;

    /**
     * Create a new message instance.
     *
     * @param string $url
     * @return void
     */
    public function __construct($url)
    {
        $this->url = $url;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.reports.ready')
                    ->subject('Tu reporte de empleados está listo');
    }
}
