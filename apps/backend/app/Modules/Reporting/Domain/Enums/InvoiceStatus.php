<?php
namespace Modules\Reporting\Domain\Enums;
enum InvoiceStatus:string { case Draft='draft'; case Issued='issued'; case Paid='paid'; case Refunded='refunded'; case Void='void'; }
