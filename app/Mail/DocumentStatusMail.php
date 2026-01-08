<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentStatusMail extends Mailable
{
    use Queueable, SerializesModels;
    
    public $employee;
    public $docVersion;
    public $files;

    /**
     * Create a new message instance.
     */
    public function __construct($employee, $docVersion, $files)
    {
        $this->employee = $employee;
        $this->docVersion = $docVersion;
        $this->files = $files;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Actualización de Estado de Documentación - ' . $this->employee->name)
                    ->view('emails.document_status');
    }
}
