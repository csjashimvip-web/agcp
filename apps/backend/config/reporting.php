<?php
return [
    'export_disk'=>env('REPORT_EXPORT_DISK','local'),
    'export_directory'=>env('REPORT_EXPORT_DIRECTORY','exports'),
    'export_retention_days'=>(int)env('REPORT_EXPORT_RETENTION_DAYS',30),
    'invoice_due_days'=>(int)env('INVOICE_DUE_DAYS',0),
    'schedule_batch_size'=>(int)env('REPORT_SCHEDULE_BATCH_SIZE',50),
];
