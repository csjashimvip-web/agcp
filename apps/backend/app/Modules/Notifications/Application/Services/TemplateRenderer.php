<?php
namespace Modules\Notifications\Application\Services;
final class TemplateRenderer
{
    public function render(string $template,array $data):string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/',function(array $m)use($data):string{
            $value=$data;
            foreach(explode('.',$m[1]) as $segment){if(!is_array($value)||!array_key_exists($segment,$value))return ''; $value=$value[$segment];}
            return is_scalar($value)?(string)$value:json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        },$template);
    }
}
