<?php
declare(strict_types=1);

namespace icePHP;
/**
 * 数组对象,扩展ArrayObject
 * 当不存在时,返回Null而不是报错
 * @author 蓝冰
 *
 */
class ArrayObject extends \ArrayObject
{

    /**
     * 构造方法
     *
     * @param array $array 要转换的数组
     */
    public function __construct(array $array)
    {
        // 调用父类构造 方法,将数组中的键定义为属性
        return parent::__construct($array, ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * 当读取不存在的属性时,返回Null,而不是报错
     *
     * @param string $name 要读取的属性名
     * @return NULL
     */
    public function __get($name)
    {
        return null;
    }

    /**
     * 当按数组方式访问时,如果下标不存在,不报错,返回Null
     *
     * @param $name string
     * @return mixed
     */
    public function offsetGet($name)
    {
        if (parent::offsetExists($name)) {
            return parent::offsetGet($name);
        }
        return null;
    }
}