<?php
declare(strict_types=1);

namespace icePHP;


class TemplateException extends \Exception
{
    //模板文件不存在
    const TEMPLATE_NOT_FOUND = 1;

}