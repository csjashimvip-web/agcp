<?php
namespace Modules\Plugins\Domain\Enums;
enum PluginInstallationStatus: string
{
    case Installed = 'installed';
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case Error = 'error';
}
