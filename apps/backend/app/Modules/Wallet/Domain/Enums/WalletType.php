<?php
namespace Modules\Wallet\Domain\Enums;
enum WalletType: string
{
    case Main = 'main';
    case Bonus = 'bonus';
    case Commission = 'commission';
    case Holding = 'holding';
    case Escrow = 'escrow';
    case Refund = 'refund';
    case VendorPayable = 'vendor_payable';
}
