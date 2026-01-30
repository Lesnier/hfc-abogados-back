<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReportReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $url;
    public $excelUrl;

    /**
     * Create a new message instance.
     *
     * @param string $url
     * @param string|null $excelUrl
     * @return void
     */
    public function __construct($url, $excelUrl = null)
    {
        $this->url = $url;
        $this->excelUrl = $excelUrl;
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
