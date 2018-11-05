<?php
declare(strict_types=1);

namespace icePHP;

/**
 * 所有控制器的基类,为控制器提供一些常用方法
 * headerto 重定向
 * prompt        在提示信息页面显示信息,并跳转到新页面
 * promptOk    显示成功信息并跳转
 * promptError    显示错误信息并跳转
 * back        显示信息并返回刚才的页面
 * ajaxOk        为Ajax返回成功信息
 * ajaxError    为Ajax返回失败信息
 */
abstract class Controller
{
    // 常用的 HTML头
    protected static $header = '
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
		<html>
			<head>		
			<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        ';

    /**
     * 302临时跳转,redirectTemporary的简略写法
     * @param $url string
     */
    protected function redirect(string $url): void
    {
        self::redirectTemporary($url);
    }

    //首页,强制转换成GET请求
    protected function index()
    {
        $this->forceGet();
    }

    /**
     * 强制将当前请求转换为GET方式,以允许浏览器后退操作
     */
    protected function forceGet(): void
    {
        //如果当前是POST方式,则进行一次重定向
        if ($_POST) {
            $this->redirect($this->url(null, $this->action, $_REQUEST));
        }
    }

    /**
     * 跳转,并返回继续工作
     * @param string $url 跳转地址
     */
    protected function redirectAndReturn(string $url): void
    {
        header("Content-Encoding: none\r\n");
        header('location:' . $url);
        echo str_repeat(' ', 1024 * 64);
        flush();
    }

    /**
     * 302 临时跳转
     * @param string $url
     */
    protected function redirectTemporary($url)
    {
        header('location:' . $url);
        ($this->callback)($this);
    }

    /**
     * 301 永久跳转
     * @param string $url
     */
    protected function redirectPermanent(string $url): void
    {
        header('HTTP/1.1 301 Moved Permanently');
        header('location:' . $url);
        ($this->callback)($this);
    }

    /**
     * 使用header重定向,让浏览器向新的地址发送请求
     * @param string $controller
     * @param string $action
     * @param array $params
     */
    protected function headerTo(string $controller = null, string $action = null, array $params = []): void
    {
        $this->redirectTemporary(url($this->module, $controller, $action, $params));
    }

    /**
     * 获取前一页URL
     */
    protected function backUrl(): string
    {
        return $this->request->referrer();
    }

    /**
     * 返回Json成功数据
     * @param mixed $data 要返回的具体数据
     */
    protected function ajaxOk($data = ''): void
    {
        $this->ajax(json(Debug::end([
            'status' => 'success',
            'success' => true,
            'error' => false,
            'data' => $data,
            'msg' => '操作成功',
            'errorCode' => 0,
        ])));
    }

    /**
     * 返回Json失败信息
     * @param string $msg 错误信息
     * @param $code int 错误代码
     * @param $data mixed 其他要返回的数据
     */
    protected function ajaxError(string $msg, int $code = 1, $data = ''): void
    {
        $this->ajax(json(Debug::end([
            'status' => 'error',
            'success' => false,
            'error' => true,
            'msg' => $msg,
            'errorCode' => $code,
            'data' => $data
        ])));
    }

    /**
     * 输出Ajax结果
     * @param string $msg
     */
    private function ajax(string $msg): void
    {
        header("Content-Type: text/html; charset=utf-8");
        $req = $this->request;

        // 前一页地址
        //$referer = $req->referer;

        // 如果是JsonP
        if ($req and $req->callback) {
            $callback = $req->callback;
            echo $callback . '(' . $msg . ')';

        } else {
            // 普通Ajax
            echo $msg;
        }

        ($this->callback)($this);
    }

    // 当前模块名称,控制器名称,动作名称
    protected $module, $controller, $action;

    /**
     * 所有请求参数对象
     * @var Request
     */
    protected $request;

    /**
     * @var callable
     */
    private $callback;

    /**
     * 初始化工作,由Frame调用,开发人员不要调用
     * @param string $module 模块名称
     * @param string $controller 控制器名称
     * @param string $action 动作名称
     * @param Request $request 请求参数
     * @param callable $callback 结束时的回调方法
     * @return boolean 如果返回False,表明不希望执行后面的动作
     */
    public function init(string $module, string $controller, string $action, Request $request, callable $callback): bool
    {
        $this->module = $module;
        $this->controller = $controller;
        $this->action = $action;
        $this->request = $request;

        $this->callback = $callback;

        // 如果当前控制器存在伪构造函数,则执行此方法
        return $this->construct($request);
    }

    /**
     * 设置返回点URL,下次请求中可以使用getBack获取此URL
     * @param $url string 要设置的url
     */
    protected function setBack(string $url = ''): void
    {
        if (empty($url)) {
            $_SESSION['_BACK_URL'] = $this->request->url();
        } else {
            $_SESSION['_BACK_URL'] = $url;
        }
    }

    /**
     * 设置返回列表页的URL,以后可以用getIndex获取此URL
     * @param string $url 要设置的URL
     */
    protected function setIndex(string $url = ''): void
    {
        if (empty($url)) {
            $_SESSION['_INDEX_URL'] = $this->request->url();
        } else {
            $_SESSION['_INDEX_URL'] = $url;
        }
    }

    /**
     * 获取返回点URL,这个地址可以传递给View,以便用户点击
     * @return string
     */
    protected function getBack()
    {
        // 如果未设置过,返回当前URL
        if (!isset($_SESSION['_BACK_URL'])) {
            return $this->request->referrer() ?: '/';
        }

        // 获取之前保存的地址
        $backUrl = $_SESSION['_BACK_URL'];

        // 获取就清除
        unset($_SESSION['_BACK_URL']);
        return $backUrl;
    }

    /**
     * 获取返回列表页的URL,这个地址用在添加/修改页面的返回按钮上
     */
    protected function getIndex(): string
    {
        // 如果未设置过,返回当前URL
        if (!isset($_SESSION['_INDEX_URL'])) {
            return $this->url('', 'index');
        }

        // 获取之前保存的地址
        $url = $_SESSION['_INDEX_URL'];

        // 获取就清除
        unset($_SESSION['_INDEX_URL']);
        return $url;
    }

    /**
     * 返回用 setBack() 设置的 URL
     * @param $url string 可以强行指定一个URL
     */
    protected function goBack(string $url = ''): void
    {
        if (!$url) {
            $url = $this->getBack();
        }
        unset($_SESSION['_BACK_URL']);
        header('Location: ' . $url);

        ($this->callback)($this);
    }

    /**
     * 缓存,针对CDN
     * @param string|int $seconds 秒数,如果不提供,自动 到午夜零点1分 00:01
     */
    protected function cacheToday($seconds = null): void
    {
        // 调试模式,不缓存
        if (isDebug()) {
            return;
        }

        // 计算秒数
        if (!$seconds) {
            $left = 24 * 60 * 60 - (time() - strtotime(date('Y-m-d'))) + 60;
        } else {
            $left = $seconds;
        }

        // 输出CDN的缓存头
        header("Cache-Control:max-age=" . $left);
        header("Pragma:public");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private", false); // required for certain browsers
        header("Expires: " . gmdate("D, d M Y H:i:s", time() + 24 * 60 * 60) . " GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    }

    /**
     * 判断是否从WWW访问
     */
    protected function mustWWW(): void
    {
        $this->must('www');
    }

    /**
     * 如果当前访问不是指定域,会跳转到首页
     * @param $domain string 域名
     */
    protected function must(string $domain): void
    {
        $req = Request::instance();

        // 如果是从WWW访问,正常返回,继续
        if ($req->domains(2) == $domain) {
            return;
        }

        // Ajax请求,直接 退出
        if ($req->isAjax) {
            ($this->callback)($this);
        }

        // 重定向到首页
        $url = str_replace($req->domains(2), $domain, $req->url());

        // 30天缓存
        $this->cacheToday(30 * 24 * 60 * 60);
        header('location:' . $url);

        ($this->callback)($this);
    }

    /**
     * 构造URL,提供一个Controller的默认值
     * @param string $controller
     * @param string $action
     * @param array $params
     * @return string
     */
    public function url(string $controller = null, string $action = null, array $params = []): string
    {
        if (!$controller) {
            $controller = $this->controller;
        }
        return Router::encode($this->module, $controller, $action, $params);
    }

    /**
     * 具体 控制器类可以选择性继承此方法来 预先检查是否可以进入动作
     * @param Request $req
     * @return bool
     */
    public function construct(Request $req): bool
    {
        //必然返回true
        return $req instanceof Request;
    }

    /**
     * 具体控制器类可以选择性使用此方法来对输出结果再次处理
     * 例如,加上头和脚
     * @return boolean
     */
    public function destruct(): bool
    {
        return true;
    }

    /**
     * 从参数中获取一个必须的整数
     * @param string $name 参数名称
     * @param string $msg 错误提示信息
     * @return int
     */
    protected function getIntMust(string $name, string $msg = ''): int
    {
        //取必须参数
        $v = $this->getMust($name, $msg);

        //去除前后空格
        $v = trim($v);

        //检查格式
        if (false === filter_var($v, FILTER_VALIDATE_INT)) {
            static::error($msg ?: '参数必须是整数(' . $name . ')');
        }

        //转化成整型返回
        return intval($v);
    }

    /**
     * 从请求参数中获取一个不是必须的整数
     * @param string $name 参数名称
     * @param int $default 默认值
     * @return int
     */
    protected function getInt(string $name, int $default = 0): int
    {
        //取参数
        $v = $this->get($name);

        //如果未指定
        if (!$v) {
            return $default;
        }

        //检查格式
        $v = trim($v);
        if (false === filter_var($v, FILTER_VALIDATE_INT)) {
            static::error('参数必须是整数(' . $name . ')');
        }

        //转化整型
        return intval($v);
    }

    /**
     * 从请求参数中获取页码参数
     */
    protected function getPage(): int
    {
        //取值
        return self::getInt('page');
    }

    /**
     * 从请求参数中获取一个自然数(0,或正整数)
     * @param $name string 参数名称
     * @param $default int 缺省值
     * @return int
     */
    protected function getNature(string $name, int $default = null): ?int
    {
        return self::getCommon('', $name, $default, function ($v) {
            if (!is_numeric($v)) {
                return ['必须是数值', null];
            }

            if (!is_int($v)) {
                return ['必须是整数', null];
            }

            $v = intval($v);
            if ($v < 0) {
                return ['必须大于等于0', null];
            }

            return ['', $v];
        });
    }

    /**
     * 从请求参数中获取一个正整数
     * @param $name string
     * @param int $default
     * @return int|null
     */
    protected function getPositive(string $name, int $default = null): ?int
    {
        return self::getCommon('', $name, $default, function ($v) {
            if (!is_numeric($v)) {
                return ['必须是数值', null];
            }

            if (!is_int($v)) {
                return ['必须是整数', null];
            }

            $v = intval($v);
            if ($v <= 0) {
                return ['必须大于0', null];
            }

            return ['', $v];
        });
    }

    /**
     * 从请求参数中获取一个字母数字串
     * @param $name string 参数名
     * @param string $default 缺省值
     * @return string
     */
    protected function getWord(string $name, string $default = null): string
    {
        return self::getCommon('', $name, $default, function ($v) {
            // 检查有效性
            if (preg_match('/^\w+$/', $v)) {
                return ['', $v];
            }
            return ['需要是字母数字', ''];
        });
    }

    /**
     * 从请求参数中获取一个布尔值 (0/1)
     * @param string $name 参数名
     * @param bool $default 缺省值
     * @return bool
     */
    protected function getBoolean(string $name, bool $default = null): bool
    {
        return self::getCommon('', $name, $default, function ($v) {
            //真
            if ($v == 1 or $v == 'on' or $v == '是' or $v == 'true') {
                return ['', true];
            }

            //假
            if ($v == 'off' or $v === '0' or $v == '否' or $v == 'false') {
                return ['', false];
            }

            // 只允许以上两个值
            return ['取值不在允许范围内', ''];
        });
    }

    /**
     * 从请求参数中获取一个浮点数
     * @param string $name 参数名
     * @param float $default 缺省值 如果不提供缺省值,那么 此参数就是必须输入的
     * @return float
     */
    protected function getFloat(string $name, float $default = null): ?float
    {
        return self::getCommon('', $name, $default, function ($v) {
            // 检查有效性
            if (false === filter_var($v, FILTER_VALIDATE_FLOAT)) {
                return ['必须是浮点数', ''];
            }
            return ['', floatval($v)];
        });
    }

    /**
     * 通用获取参数值的方法
     * @param string $title 参数的表意名称
     * @param string $name 参数名称
     * @param $default mixed 缺省值
     * @param callable $check 验证方法,需要返回[$error,$val]
     * @return mixed
     */
    private function getCommon(string $title, string $name, $default, callable $check)
    {
        $v = self::get($name);
        if (is_null($v)) {
            if (!is_null($default)) {
                return $default;
            }
            static::error("{$title}参数[$name]必须输入");
        }

        [$error, $val] = $check($v);
        if ($error) {
            static::error("{$title}参数" . $error);
        }
        return $val;
    }

    /**
     * 获取一个大于0的金额
     * @param string $name 参数名称
     * @param float $default 缺省值
     * @return float
     */
    protected function getPositiveMoney(string $name = 'money', float $default = null): ?float
    {
        return self::getCommon('金额', $name, $default, function ($v) {
            if (!is_numeric($v)) {
                return ['必须是数值', null];
            }

            if (!preg_match('/^\d+(\.\d{1,2})?$/', $v)) {
                return ['格式错误', null];
            }

            $v = floatval($v);
            if ($v <= 0) {
                return ['必须大于0', null];
            }

            return ['', $v];
        });
    }

    /**
     * 从请求参数中获取一个金额
     * @param string $name 参数名
     * @param float $default 缺省值
     * @return float
     */
    protected function getMoney(string $name = 'money', float $default = null): ?float
    {
        return self::getCommon('金额', $name, $default, function ($v) {
            if (!is_numeric($v)) {
                return ['必须是数值', null];
            }

            if (!preg_match('/^\-?\d+(\.\d{1,2})?$/', $v)) {
                return ['格式错误', null];
            }

            $v = floatval($v);
            return $v;
        });
    }

    /**
     * 从请求中获取ID(编号),不是必须指定
     * @param string $name 参数名称,默认为id(小写)
     * @param int|string $defaultOrMessage 默认值或者是错误提示
     * @return int
     */
    protected function getId(string $name = 'id', $defaultOrMessage = 0): int
    {
        if (is_int($defaultOrMessage)) {
            return $this->getInt($name, $defaultOrMessage);
        }
        if (is_string($defaultOrMessage)) {
            return $this->getIntMust($name, $defaultOrMessage);
        }

        trigger_error('getId方法的第二个参数,必须是整型或字符串', E_USER_ERROR);
    }

    /**
     * 从请求参数中获取 整数 列表(逗号分隔),也可以是真正的数组
     * @param string $name 参数名
     * @param bool $must 是否是必须的
     * @return array
     */
    protected function getIds(string $name = 'ids', bool $must = true): array
    {
        return self::getCommon('编号列表', $name, $must ? null : [], function ($v) {
            if (is_array($v)) {
                foreach ($v as $item) {
                    if (!preg_match('/^\d+(,\d+)*$/', $item)) {
                        return ['格式错误', ''];
                    }
                }
                return ['', $v];
            }
            // 檢查有效性
            if (!preg_match('/^\d+(,\d+)*$/', $v)) {
                return ['格式错误', ''];
            }

            // 分解成数组返回
            return ['', explode(',', $v)];
        });
    }

    /**
     * 从请求参数中获取分页参数
     * @return array(offset,length)
     */
    protected function getLimit(): array
    {
        //取值
        $offset = self::getInt('offset', 0);
        $length = self::getInt('length', 10);

        // offset不能小于0
        if ($offset < 0) {
            static::error('分页偏移量至少为0');
        }

        // length不能小于1
        if ($length < 1) {
            static::error('分页长度至少为1');
        }

        // 返回数组形式
        return [$offset, $length];
    }

    /**
     * 从请求参数中获取性别:男/女/未知
     * @param $name string 参数名称
     * @param $default string 缺省值
     * @return string 男/女/未知
     */
    protected function getSex(string $name = 'sex', string $default = null): string
    {
        return self::getCommon('性别', $name, $default, function ($v) {
            // 判定取值是否允许
            if (in_array($v, ['男', '女', '未知'])) {
                return ['', $v];
            }
            return ['无法识别', ''];
        });
    }

    /**
     * 从输入参数中获取密码参数(Password)
     * @param $name string 参数名称
     * @return string
     */
    protected function getPassword(string $name = 'password'): string
    {
        return self::getMust($name);
    }

    /**
     * 从请求参数中获取邮箱地址(Email)
     * @param bool $must 是否是必须的
     * @return string
     */
    protected function getEmail(bool $must = true): string
    {

        return self::getCommon('邮箱地址', 'email', $must ? null : '', function ($v) {
            if (false === filter_var($v, FILTER_VALIDATE_EMAIL)) {
                return ['格式错误', ''];
            }
            return ['', $v];
        });
    }

    /**
     * 从请求中获取一个必须的字符串参数,过滤掉HTML标签
     * @param string $name 参数名称
     * @param string $msg 错误提示
     * @return string
     */
    protected function getStringMust(string $name, string $msg = ''): string
    {
        //获取一个必要参数
        $v = $this->getHtmlMust($name, $msg);

        //去除引号和反斜线
        $v = str_replace(['\'', '"', '\\'], '', $v);
        return strip_tags($v);
    }

    /**
     * 从请求中获取一个不是必须的字符串参数,过滤掉HTML标签
     * @param string $name 参数名称
     * @param string $default 默认值
     * @return string
     */
    protected function getString(string $name, string $default = ''): string
    {
        //获取一个不必要参数
        $v = $this->getHtml($name, $default);

        //去除引号和反斜线
        $v = str_replace(['\'', '"', '\\'], '', $v);
        return strip_tags($v);
    }


    /**
     * 检查字符串是否是非法编码 (无法转换成gb2312/gbk)
     * @param $v string 待检查的字符串
     * @return bool true:无法成功转换
     */
    static private function codeError(string $v): bool
    {
        $special = configDefault('', 'anti', 'utf-8');
        return iconv('utf-8', 'gb2312', str_replace($special, '', $v)) === false
            and iconv('utf-8', 'gbk', str_replace($special, '', $v)) === false;
    }

    /**
     * 从请求参数中获取一个单值,可以指定 或不指定
     * @param string $name 参数名称
     * @return string|null 未指定时返回null
     */
    private function getOneBase(string $name): ?string
    {
        //取值
        $v = $this->request->$name;

        // 不存在则返回 null
        if (!isset($this->request[$name]) or $v === '' or is_null($v)) {
            return null;
        }

        //不能是数组
        if (is_array($v)) {
            static::error('参数格式错误:' . $name);
        }

        //不可能
        if (!is_string($v)) {
            static::error($name . '参数类型即不是数组也不是字符串:' . gettype($v));
        }

        //不能有编码问题
        if (self::codeError($v)) {
            static::error('参数编码错误:' . $name);
        }
        return $v;
    }

    /**
     * 从请求参数中获取一个单值,必须指定
     * @param string $name 参数名称
     * @param string $msg 错误提示
     * @return string
     */
    protected function getHtmlMust(string $name, string $msg = ''): string
    {
        //取值
        $v = $this->getOneBase($name);

        // 必须存在
        if (is_null($v)) {
            static::error($msg ?: '请求缺少必须的参数(' . $name . ')');
        }
        return $v;
    }

    /**
     * 从请求参数中获取一个单值,可以不指定,有默认值
     * @param string $name 参数名称
     * @param string $default 默认值
     * @return string
     */
    protected function getHtml(string $name, string $default = null): string
    {
        //取值
        $v = $this->getOneBase($name);

        // 不存在时,返回默认值
        if (is_null($v)) {
            return $default;
        }
        return $v;
    }

    /**
     * 从请求参数中获取一个数组,可以指定或不指定
     * @param string $name 参数名称
     * @return array|null 不指定时返回null
     */
    private function getArrBase(string $name): ?array
    {
        //取值
        $v = $this->request->$name;

        // 不存在时返回Null
        if (!isset($this->request[$name]) or $v === '' or is_null($v)) {
            return null;
        }

        //必须是数组
        if (!is_array($v)) {
            static::error('参数必须是数组:' . $name);
        }

        //逐个元素检查编码问题
        foreach ($v as $value) {
            if (self::codeError($value)) {
                static::error('参数编码错误:' . $name);
            }
        }

        //返回数组
        return $v;
    }

    /**
     * 从请求参数中获取一个数组,必须指定
     * @param string $name 参数名称
     * @return array
     */
    protected function getArrMust(string $name): array
    {
        //取值
        $v = $this->getArrBase($name);

        // 必须存在
        if (is_null($v)) {
            static::error('请求缺少必须的参数(' . $name . ')');
        }

        //返回数组
        return $v;
    }

    /**
     * 从请求参数中获取一个数组,可以不指定,有默认值
     * @param string $name 参数名称
     * @param array $default 默认值
     * @return array
     */
    protected function getArr(string $name, array $default = []): array
    {
        //取值
        $v = $this->getArrBase($name);

        // 如果未指定,返回默认值
        if (is_null($v)) {
            return $default;
        }

        //返回数组
        return $v;
    }


    /**
     * 从请求中获取一个必须的字符串参数,getStringMust的简略写法
     * @param string $name 参数名称
     * @param string $msg 错误信息
     * @return string
     */
    protected function getMust(string $name, string $msg = ''): string
    {
        return $this->getStringMust($name, $msg);
    }

    /**
     * 从请求中获取三个不必须的字符串参数,getString的简略写法
     * @param string $name 参数名称
     * @param string $default 默认值
     * @return string
     */
    protected function get(string $name, string $default = ''): string
    {
        return $this->getString($name, $default);
    }

    /**
     * 从请求参数中获取一个日期(yyyy-mm-dd)
     * @param string $name 参数名
     * @param bool $must 是否必须提供
     * @return string
     */
    protected function getDate(string $name = 'date', bool $must = true): ?string
    {
        return self::getCommon('日期', $name, $must ? null : '', function ($v) {
            // 检查格式
            if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $v)) {
                return ['格式错误', ''];
            }
            return ['', $v];
        });
    }

    /**
     * 从请求参数中获取小时参数(0-24)
     * @param string $name 参数名称
     * @param int $default 缺省值
     * @return null|int
     */
    protected function getHour(string $name = 'hour', int $default = null): ?int
    {
        return self::getCommon('小时', $name, $default, function ($v) {
            if (!is_int($v)) {
                return ['格式错误', ''];
            }
            $v = intval($v);
            if ($v < 0 or $v > 24) {
                return ['超出范围', ''];
            }

            return ['', $v];
        });
    }

    /**
     * 从请求参数中获取一个时间参数, H:i:s
     * @param string $name 参数名称,默认为time
     * @param string $default 缺省值
     * @return string
     */
    protected function getTime(string $name = 'time', string $default = null): string
    {
        return self::getCommon('时间', $name, $default, function ($v) {
            // 检查格式
            if (preg_match('/^\d{2}\:\d{2}\:\d{2}$/', $v)) {
                return ['', $v];
            }
            return ['格式错误', ''];
        });
    }

    /**
     * 从请求参数中获取一个时间参数,Y-m-d H:i:s
     * @param string $name 参数名称,默认为time
     * @param bool $must 是否必须提供
     * @return string
     */
    protected function getDateTime(string $name = 'datetime', bool $must = false): ?string
    {
        return self::getCommon('时间', $name, $must ? null : '', function ($v) {
            // 检查格式
            if (!preg_match('/^\d{4}\-\d{2}\-\d{2}\s*\d{2}\:\d{2}\:\d{2}$/', $v)) {
                return ['格式错误', ''];
            }
            return ['', $v];
        });
    }

    /**
     * 从请求参数中获取一个时间参数,Y-m-d H:i
     * @param string $name 参数名称,默认为time
     * @return string
     */
    protected function getNoMinuteTime(string $name = 'datetime'): ?string
    {
        return self::getCommon('日期时间', $name, '', function ($v) {
            // 检查格式
            if (!preg_match('/^\d{4}\-\d{2}\-\d{2}\s*\d{2}\:\d{2}$/', $v)) {
                return ['格式错误', ''];
            }
            return ['', $v];
        });
    }

    /**
     * 从输入参数中获取姓名参数(Name)
     * @param bool $must 是否必须提供
     * @return string
     */
    protected function getName(bool $must = true): string
    {
        if ($must) {
            return self::getMust('name');
        }
        return self::get('name');
    }

    /**
     * 从输入参数中获取昵称参数(Nick)
     */
    protected function getNick(): string
    {
        $v = self::get('nick');

        // 过滤特殊码表字符
        $v = json_decode(preg_replace('/(\\\u[d-f][0-9a-f]{3})/i', '', json_encode($v)));

        return $v;
    }

    /**
     * 从请求参数中获取一个数组,可以是真正的数组,也可以是逗号分隔的
     * @param string $name 参数名称
     * @param bool $must 是否必须
     * @return array
     */
    protected function getArray(string $name, bool $must = true): array
    {
        return self::getCommon('', $name, $must ? null : [], function ($v) {
            if (is_array($v)) {
                return ['', $v];
            }
            $v = explode(',', $v);
            return ['', $v];
        });
    }

    /**
     * 从请求参数中获取手机号码(mobile)参数
     * @param $must bool 是否必须输入
     * @return string
     */
    protected function getMobile(bool $must = true): ?string
    {
        return self::getCommon('手机号码', 'mobile', $must ? null : '', function ($v) {
            // 正则,1开头,11位数字
            if (preg_match('/^1[3|4|5|7|8|9]\d{9}$/', $v)) {
                return ['', $v];
            }
            return ['格式错误', ''];
        });
    }


    /**
     * 从请求参数中获取手机号码,
     * @param string $name 参数名默认为phone
     * @param bool $must 是否必须
     * @return null|string
     * @Author 阿顿
     * @Version 1.1.1版
     */
    protected function getPhone(string $name = 'phone', bool $must = true): ?string
    {
        return self::getCommon('手机号码', $name, $must ? null : '', function ($v) {
            // 正则,1开头,11位数字
            if (preg_match('/^1[3|4|5|7|8|9]\d{9}$/', $v)) {
                return ['', $v];
            }
            return ['格式错误', ''];
        });
    }

    /**
     * 从请求参数中获取验证码(vCode,4位)参数
     * @param string $name 参数名称
     * @return string 验证码
     */
    protected function getVCode(string $name = 'vCode'): string
    {
        return self::getCommon('验证码', $name, null, function ($v) {
            if (preg_match('/^[\d\w]{4}$/', $v)) {
                return ['', $v];
            }
            return ['格式错误', ''];
        });
    }

    /**
     * 从请求参数中获取经纬度(lng,lat)
     * @return array of lng,lat
     */
    protected function getPos(): array
    {
        //取值
        $lng = $this->getFloat('lng'); // 经度
        $lat = $this->getFloat('lat'); // 纬度

        //检查值的范围
        if ($lng < -180 or $lng > 180 or $lat < -180 or $lat > 180) {
            static::error('经纬度必须在正负180以内');
        }

        return [$lng, $lat];
    }

    /**
     * 递归方式对数据进行编码
     * 主要 是去除 null,bool,数值 , 都以字符串方式表现
     *
     * @param mixed $data 任意数据
     * @return string|array
     */
    private function encode($data)
    {
        // 已经是字符串
        if (is_string($data)) {
            return $data;
        }

        // 数值
        if (is_numeric($data)) {
            return '' . $data;
        }

        // 空
        if (is_null($data)) {
            return '';
        }

        // 布尔,转换成1/0 1为真,0为假
        if (is_bool($data)) {
            return $data ? '1' : '0';
        }

        // 如果是行对象或结果集对象,调用方法转为数组
        if (is_object($data) and method_exists($data, 'toArray')) {
            $data = $data->toArray();
        }

        // 如果不是数组,那就不认识了,强行转换为数组
        if (!is_array($data)) {
            $data = (array)$data;
        }

        // 结果数组
        $ret = [];
        foreach ($data as $k => $v) {
            // 递归编码数组元素
            $ret[$k] = $this->encode($v);
        }

        // 返回结果数组
        return $ret;
    }

    /**
     * 从请求参数中获取一个键值对列表( 数值:数值|数值:数值......)
     * @param $name string 参数名称
     * @return array
     */
    protected function getKVList(string $name = 'list'): array
    {
        // 取字符串参数
        $list = $this->get($name);
        if (!$list) {
            return [];
        }

        // 划分键值对数组
        $arr = explode('|', $list);
        $ret = [];

        // 逐个拆分
        foreach ($arr as $item) {
            $a = explode(':', $item);
            $ret[intval($a[0])] = intval($a[1]);
        }
        return $ret;
    }

    /**
     * 从参数中取手机型号及编码
     * @return array [type,code]
     */
    protected function getDevice(): array
    {
        return [
            'type' => self::getEnum('type', ['Android', 'IOS']),
            'code' => self::get('code')
        ];
    }

    /**
     * 设置一个错误信息,并返回上一次的保存点
     * @param $msg string 要显示的信息
     * @param $url string 可以强行指定一个URL用来跳转
     */
    protected function errorBack(string $msg, string $url = ''): void
    {
        //设置错误信息
        Message::setError($msg);

        //返回保存点
        $this->goBack($url);
    }

    /**
     * 设置一个成功信息,并返回上一次的保存点
     * @param $msg string 要显示的信息
     * @param $url string 可以强行指定一个URL用来跳转
     */
    protected function successBack(string $msg, string $url = ''): void
    {
        //设置成功信息
        Message::setSuccess($msg);

        //返回保存点
        $this->goBack($url);
    }

    /**
     * 完成错误信息的显示并跳转
     * @param $msg mixed 提示信息
     * @param string $url 要跳转的地址
     * @param $code int 错误编码
     */
    protected function error(string $msg, string $url = '', int $code = 1): void
    {
        //如果当前是Ajax请求,以Ajax方式返回错误
        if (isAjax()) {
            $this->ajaxError($msg, $code);
        } elseif ($this->request->referrer()) {
            //如果有上一页,将错误返回给上一页
            self::errorBack($msg, $url);
        } else {
            //直接显示到错误页面
            trigger_error($msg, E_USER_ERROR);
        }
    }

    /**
     * 完成成功信息的显示并跳转
     * @param $msg mixed 提示信息
     * @param string $url 要跳转的地址
     */
    protected function ok($msg = '', string $url = ''): void
    {
        if (isAjax()) {
            $this->ajaxOk($msg);
        } else {
            self::successBack($msg, $url);
        }
    }

    /**
     * 从请求参数中获取多个字符串参数
     * @param array $names 参数名数组
     * @return array 参数值数组
     */
    protected function getMulti(array $names): array
    {
        $ret = [];
        foreach ($names as $name) {
            $ret[$name] = $this->get($name);
        }
        return $ret;
    }

    /**
     * 从请求参数中获取地址 四段 信息,包括 省/市/区/详细地址
     * @return array
     */
    protected function getAddress(): array
    {
        return [
            'provinceId' => $this->getIdMust('provinceId'), //省份编号
            'cityId' => $this->getIdMust('cityId'), //城市编号
            'areaId' => $this->getIdMust('areaId'), //区县编号
            'address' => $this->getMust('address') //详细地址
        ];
    }

    /**
     * 获取指定范围值
     * @param $name string 参数名
     * @param $enum array 允许的取值范围
     * @param bool $must
     * @return mixed|string
     */
    protected function getEnum(string $name, array $enum, bool $must = true): ?string
    {
        return self::getCommon('', $name, $must ? null : '', function ($v) use ($enum) {
            if (in_array($v, $enum)) {
                return ['', $v];
            }
            return ['取值不在允许的范围内', ''];
        });
    }

    /**
     * 从请求参数中获取一个值,并在外键表中判断是否存在
     * @param $name string 参数名称
     * @param $table string 外键表名称
     * @param $key string 外键表值字段名称
     * @param $where string 外键表搜索条件
     * @param bool $must 是否必须存在
     * @return null|string
     */
    protected function getForeign(string $name, string $table, string $key, string $where, bool $must = true): ?string
    {
        return self::getCommon('', $name, $must ? null : '', function ($v) use ($table, $key, $where) {
            //在外键表中检查存在性
            if (!table($table)->exist(($where ? $where . ' AND ' : '') . ' `' . $key . '`=\'' . $v . '\'')) {
                return ['参数值不在允许范围内', ''];
            }
            return $v;
        });
    }

    /**
     * 从请求参数中获取一个字典字段的值,并在字典表中判断是否合法
     * @param $name string 参数名称
     * @param $table string 字典表名称
     * @param $nameField string 字典表名字字段名称
     * @param $valueField string 字典表值字段名称
     * @param $separator string 分隔符
     * @param bool $must
     * @return null|string
     */
    protected function getDict(string $name, string $table, string $nameField, string $valueField, string $separator, bool $must = true): ?string
    {
        return self::getCommon('', $name, $must ? null : '', function ($v) use ($table, $name, $nameField, $valueField, $separator) {
            //检查合法性
            if (!in_array($v, explode($separator, table($table)->get($valueField, [$nameField => $name])))) {
                return ['取值不在允许范围内', ''];
            }
            return ['', $v];
        });
    }

    /**
     * 从请求参数中获取分钟参数(0-59)
     * @param string $name 参数名称
     * @param int $default 缺省值
     * @return null|int
     */
    protected function getMinute(string $name = 'minute', int $default = null): ?int
    {
        return self::getCommon('分钟', $name, $default, function ($v) {
            if (!is_int($v)) {
                return ['格式错误', ''];
            }
            $v = intval($v);
            if ($v < 0 or $v > 59) {
                return ['超出范围', ''];
            }

            return ['', $v];
        });
    }

    /**
     * 从请求参数中获取年份参数
     * @param string $name 参数名称
     * @param int $default 缺省值
     * @return null|int
     */
    protected function getYear(string $name = 'year', int $default = null): int
    {
        return self::getCommon('年份', $name, $default, function ($v) {
            if (is_int($v)) {
                return ['', $v];
            }

            return '格式错误';
        });
    }

    //强制本次请求为Ajax
    protected function forceAjax(): void
    {
        isAjax(true);
    }
}