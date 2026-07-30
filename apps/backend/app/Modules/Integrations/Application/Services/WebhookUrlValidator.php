<?php
namespace Modules\Integrations\Application\Services;
use Illuminate\Validation\ValidationException;
final class WebhookUrlValidator
{
    public function validate(string $url):void
    {
        if(str_starts_with($url,'log://'))return;$parts=parse_url($url);if(!is_array($parts)||($parts['scheme']??'')!=='https'||empty($parts['host']))throw ValidationException::withMessages(['url'=>'Webhook URL must use HTTPS or the local log:// sink.']);
        $host=(string)$parts['host'];if(in_array(strtolower($host),['localhost','127.0.0.1','::1'],true)||str_ends_with(strtolower($host),'.local'))throw ValidationException::withMessages(['url'=>'Private or local webhook hosts are not allowed.']);
        $ips=gethostbynamel($host)?:[];foreach($ips as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw ValidationException::withMessages(['url'=>'Webhook host resolves to a private or reserved address.']);
    }
}
