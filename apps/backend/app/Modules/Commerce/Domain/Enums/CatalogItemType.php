<?php
namespace Modules\Commerce\Domain\Enums;
enum CatalogItemType: string
{
    case Physical = 'physical';
    case Digital = 'digital';
    case Service = 'service';
}
