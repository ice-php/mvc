<?php
declare(strict_types=1);

namespace icePHP;


class TemplateException extends \Exception
{
    //模板文件不存在
    const TEMPLATE_NOT_FOUND = 1;

    //模板中不允许直接使用PHP
    const PHP_DISABLED=2;

    //缺少SEO相关配置(application|seo)
    const SEO_CONFIG_UNEXISTS=3;
}