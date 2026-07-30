<?php
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Commerce\Domain\Enums\OrderStatus;
use Modules\Commerce\Infrastructure\Models\Order;
use Modules\Reporting\Application\Services\DataExportService;
use Modules\Reporting\Application\Services\InvoiceDocumentService;
use Modules\Reporting\Application\Services\InvoiceService;
use Modules\Reporting\Application\Services\ReportScheduleService;
use Modules\Reporting\Infrastructure\Models\ReportSchedule;
use Modules\Reporting\Infrastructure\Models\TaxRate;
use Modules\Reporting\Infrastructure\Models\TenantTaxProfile;
use Modules\Tenancy\Infrastructure\Models\Tenant;
use Modules\Wallet\Domain\Enums\AccountType;
use Modules\Wallet\Domain\Enums\LedgerDirection;
use Modules\Wallet\Domain\Enums\WalletType;
use Modules\Wallet\Infrastructure\Models\LedgerAccount;
use Modules\Wallet\Infrastructure\Models\Wallet;
uses(RefreshDatabase::class);
function reportingFixture():array{$tenant=Tenant::query()->create(['name'=>'Reporting Tenant','slug'=>'reporting-tenant','status'=>'active','default_currency'=>'USD','timezone'=>'UTC']);$user=User::query()->create(['name'=>'Invoice Customer','email'=>'invoice@example.test','password'=>'Secret123!','status'=>'active','email_verified_at'=>now()]);$account=LedgerAccount::query()->create(['tenant_id'=>$tenant->id,'code'=>'customer-'.$user->id,'name'=>'Customer wallet','account_type'=>AccountType::Liability,'normal_balance'=>LedgerDirection::Credit,'owner_type'=>User::class,'owner_id'=>$user->id,'currency'=>'USD','balance_minor'=>10000,'status'=>'active']);$wallet=Wallet::query()->create(['tenant_id'=>$tenant->id,'owner_type'=>User::class,'owner_id'=>$user->id,'ledger_account_id'=>$account->id,'type'=>WalletType::Main,'currency'=>'USD','status'=>'active']);$order=Order::query()->create(['tenant_id'=>$tenant->id,'user_id'=>$user->id,'wallet_id'=>$wallet->id,'number'=>'ORD-REPORT-1','status'=>OrderStatus::Confirmed,'payment_status'=>'paid','fulfillment_status'=>'unfulfilled','currency'=>'USD','subtotal_minor'=>10000,'discount_minor'=>1000,'surcharge_minor'=>0,'total_minor'=>9000,'placed_at'=>now()]);$order->items()->create(['item_name'=>'Digital service','variant_name'=>'Standard','sku'=>'SERVICE-1','item_type'=>'service','quantity'=>1,'unit_price_minor'=>10000,'total_minor'=>10000,'status'=>'pending']);TenantTaxProfile::query()->create(['tenant_id'=>$tenant->id,'legal_name'=>'Reporting Tenant Ltd','invoice_prefix'=>'RPT','next_invoice_sequence'=>1,'default_tax_behavior'=>'inclusive','status'=>'active']);TaxRate::query()->create(['tenant_id'=>$tenant->id,'code'=>'VAT10','name'=>'VAT 10%','rate_basis_points'=>1000,'price_inclusive'=>true,'applies_to'=>'service','status'=>'active']);return compact('tenant','user','order');}
it('generates a tax-inclusive immutable invoice without changing the paid order total',function(){$f=reportingFixture();$invoice=app(InvoiceService::class)->generate($f['order'],$f['user']);expect($invoice->number)->toBe('RPT-'.now()->format('Y').'-000001')->and($invoice->total_minor)->toBe(9000)->and($invoice->amount_due_minor)->toBe(0)->and($invoice->tax_minor)->toBeGreaterThan(0)->and(strlen($invoice->content_hash))->toBe(64)->and($invoice->lines)->toHaveCount(2);});
it('returns the existing invoice when generation is replayed',function(){$f=reportingFixture();$first=app(InvoiceService::class)->generate($f['order'],$f['user']);$second=app(InvoiceService::class)->generate($f['order'],$f['user']);expect($second->id)->toBe($first->id)->and(\Modules\Reporting\Infrastructure\Models\Invoice::query()->count())->toBe(1);});
it('renders a document containing the integrity hash',function(){$f=reportingFixture();$invoice=app(InvoiceService::class)->generate($f['order'],$f['user']);$html=app(InvoiceDocumentService::class)->html($invoice);expect($html)->toContain($invoice->number)->and($html)->toContain($invoice->content_hash);});
it('creates a checksummed invoice CSV export',function(){Storage::fake('local');$f=reportingFixture();app(InvoiceService::class)->generate($f['order'],$f['user']);$export=app(DataExportService::class)->create($f['tenant']->id,'invoices',$f['user']);expect($export->status->value)->toBe('completed')->and($export->row_count)->toBe(1)->and(strlen((string)$export->checksum_sha256))->toBe(64);Storage::disk('local')->assertExists($export->storage_path);});
it('runs a scheduled report and records its export',function(){Storage::fake('local');$f=reportingFixture();app(InvoiceService::class)->generate($f['order'],$f['user']);$schedule=ReportSchedule::query()->create(['tenant_id'=>$f['tenant']->id,'created_by'=>$f['user']->id,'name'=>'Invoice report','report_type'=>'invoices','frequency'=>'monthly','timezone'=>'UTC','enabled'=>true,'next_run_at'=>now()]);$run=app(ReportScheduleService::class)->run($schedule,$f['user']);expect($run->status)->toBe('completed')->and($run->data_export_id)->not->toBeNull()->and($schedule->fresh()->last_run_at)->not->toBeNull();});
