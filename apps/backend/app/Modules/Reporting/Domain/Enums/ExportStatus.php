<?php
namespace Modules\Reporting\Domain\Enums;
enum ExportStatus:string { case Queued='queued'; case Processing='processing'; case Completed='completed'; case Failed='failed'; case Expired='expired'; }
