<?php
declare(strict_types=1);

namespace icePHP;

/**
 * 所有业务逻辑类的基类,静态
 */
class Model
{
    /**
     * 禁止实例化
     */
    private function __construct()
    {
    }

    /**
     * 伪构造方法,在类被载入时会被执行
     * 没有参数,不需要返回值
     * 子类可以覆盖以执行具体的初始化操作,如:设置钩子
     */
    public static function construct(): void
    {

    }
}