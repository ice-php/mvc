<?php
declare(strict_types=1);

namespace icePHP;
/**
 * 全部页面控件,调用方法:在模板中 {:静态方法名(参数表)}
 * User: Ice
 * Date: 2016/10/28
 * Time: 8:31
 */
class ViewUnit
{
    /**
     * 显示一个返回列表的按钮
     *
     * @param string $url 返回的地址
     * @param string $title 可选,按钮标题
     * @throws \Exception
     */
    static public function back(string $url, string $title = ''): void
    {
        display('form/return', [
            'url' => $url,
            'title' => $title
        ]);
    }

    /**
     * 显示一个新增按钮
     *
     * @param string $url 地址
     * @param string $title 按钮文字
     * @throws \Exception
     */
    static public function add(string $url, string $title = ''): void
    {
        display('form/add', [
            'url' => $url,
            'title' => $title
        ]);
    }

    /**
     * 显示面包屑,带任意多的参数,格式为名称=>地址 或 单独名称
     * @param $list array
     * @throws \Exception
     */
    static public function crumb(array $list): void
    {
        display('layout/crumb', ['list' => $list]);
    }

    /**
     * 显示一个日期控件
     *
     * @param array $config
     *            label 字段标题
     *            name 字段的名字
     *            info 整个行对象
     *            value 字段的值 ,本字段与info字段至少要提供一个,否则值为空
     *            prompt 提示信息,
     *            max 要求最大值为今天
     * @throws \Exception
     */
    static public function date(array $config): void
    {
        display('form/date', $config);
    }

    /**
     * 计算折扣
     *
     * @param float $now 当前价
     * @param float $old 原价
     * @param int $pres 精度,默认为小数后一位
     * @return string number
     */
    static public function discount(float $now, float $old, int $pres = 1): string
    {
        // 参数安全处理
        $now = floatval($now);
        $old = floatval($old);

        // 如果当前价无,则是0折
        if (!$now) {
            return '0';
        }

        // 如果原价无,则10折
        if (!$old) {
            return '10';
        }

        // 如果当前价大于原价,则10折
        if ($now > $old) {
            return '10';
        }

        // 否则计算折扣
        return '' . round($now * 10 / $old, $pres);
    }

    /**
     * 美化金额
     *
     * @param float $money
     * @return  float
     */
    static public function money(float $money): float
    {
        // 参数安全处理
        $money = floatval($money);

        // 如果金额无,则显示0
        if (!$money) {
            return 0;
        }

        // 也没什么别的处理了
        return round($money, 2);
    }

    /**
     * 取样式表文件路径
     */
    static public function pathCss(): string
    {
        return Template::pathCss();
    }

    /**
     * 取脚本文件路径
     */
    static public function pathJs(): string
    {
        return Template::pathJs();
    }


    /**
     * 取所有静态资源所在路径
     */
    static public function pathStatic(): string
    {
        return Template::pathStatic();
    }

    /**
     * 获取图片文件根路径
     */
    static public function pathImg(): string
    {
        return Template::pathImg();
    }


    /**
     * 返回根路径,去除最后的斜线
     */
    static public function pathRoot(): string
    {
        return Template::pathRoot();
    }

    /**
     * 隐藏手机号码中间三位
     *
     * @param string $phone
     * @return string
     */
    static public function phone(string $phone): string
    {
        return substr($phone, 0, 4) . '***' . substr($phone, -4);
    }

    /**
     * 生成SEO相关内容
     *
     * @param string $page
     * @param array $params
     * @return string
     */
    static public function seo(string $page, array $params = []): string
    {
        // 取全部SEO配置信息
        $rules = config('application', 'seo');
        if (!isset($rules[$page])) {
            $page = '_default';
        }

        // 取本SEO配置 信息
        $rule = $rules[$page];

        // SEO三项
        $title = $rule['title'];
        $keywords = $rule['keywords'];
        $description = $rule['description'];

        // 替换,因变量不会太多,逐个替换
        foreach ($params as $key => $value) {
            $title = str_replace('{$' . $key . '}', $value, $title);
            $keywords = str_replace('{$' . $key . '}', $value, $keywords);
            $description = str_replace('{$' . $key . '}', $value, $description);
        }

        // 构造 HTML中SEO三项的语法
        $ret = '<title>' . $title . '</title>' . "\n";
        $ret .= '<meta name="keywords" content="' . $keywords . '"/>' . "\n";
        $ret .= '<meta name="description" content="' . $description . '"/>' . "\n";

        return $ret;
    }

    /**
     * 对超长字符串进行截断
     *
     * @param string $v 源字符串
     * @param int $len 允许的最大长度
     * @return string 截断后的串
     */
    static public function short(?string $v, int $len = 20): string
    {
        if (!$v) {
            return '';
        }
        if (mb_strlen($v, 'utf-8') <= $len) {
            return $v;
        }

        $v = mb_substr($v, 0, $len - 3, 'utf-8') . '...';
        return $v;
    }

    /**
     * 显示一个不可编辑的文本
     * @param array $config
     *            label 字段标题
     *            name 字段的名字
     *            info 整个行对象
     *            value 字段的值 ,本字段与info字段至少要提供一个,否则值为空
     *            prompt 提示信息
     * @throws \Exception
     */
    static public function string(array $config): void
    {
        display('form/string', $config);
    }

    /**
     * 显示一个表单提交按钮
     * @param string $title
     * @throws \Exception
     */
    static public function submit(string $title = '提交'): void
    {
        display('form/submit', ['title' => $title]);
    }

    /**
     * 显示一个不可编辑的表格
     * @param array $config
     *            label 字段标题
     *            name 字段的名字
     *            info 整个行对象
     *            value 字段的值 ,本字段与info字段至少要提供一个,否则值为空
     *            prompt 提示信息
     * @throws \Exception
     */
    static public function table(array $config): void
    {
        display('form/table', $config);
    }

    /**
     * 获取当前版本号
     * @return string
     */
    static public function ver(): string
    {
        return config('system', 'version');
    }

    /**
     * 按单词对长段落进行活力
     * @param string $str 原文
     * @param int $num 单词数
     * @return string 省略后的文字
     */
    static public function wordLimit(string $str, int $num = 100): string
    {
        if (strlen($str) < $num) {
            return $str;
        }

        $word = preg_split('/\s/u', $str, -1, PREG_SPLIT_NO_EMPTY);

        if (count($word) <= $num) {
            return $str;
        }

        $str = "";

        for ($i = 0; $i < $num; $i++) {
            $str .= $word[$i] . " ";
        }

        return trim($str) . '&#8230;';
    }
}