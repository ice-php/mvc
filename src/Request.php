<?php
declare(strict_types=1);

namespace icePHP;

/**
 * 访问请求类
 */
final class Request extends ArrayObject
{
    //由于SArrayObject要求必须允许外部访问
    //但本类 不希望 外部实例化
    public function __construct()
    {
        // 防入侵处理
        self::antiHtml();

        // 处理编码过的中文GET参数
        self::urlDecode();

        // 构造成为ArrayObject
        parent::__construct($_REQUEST);

        // 数据
        $this->_datas = $_REQUEST;
    }

    //单例句柄
    private static $instance;

    // 获取单例实例
    public static function instance(): Request
    {
        if (!static::$instance) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    // 数据
    protected $_datas;

    /**
     * 获取请求体
     * @return string
     */
    public function body(): string
    {
        if (!$this->isPost()) {
            return '';
        }
        return @file_get_contents('php://input');
    }

    /**
     * 获取代理信息
     * @return string
     */
    public function agent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * 获取当前的域名
     * @return string
     */
    public function host(): string
    {
        if (isCliMode()) {
            return $_SERVER['HTTP_HOST'];
        }
        return parse_url($this->url(), PHP_URL_HOST);
    }

    /**
     * 判断是否是POST提交
     * @return bool
     */
    public function isPost(): bool
    {
        return (!empty($_POST) or !empty($_FILES));
    }

    /**
     * 检查GET参数中的经过URLEncode过后汉字
     * 修改$_REQUEST
     */
    private function urlDecode(): void
    {
        foreach ($_GET as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    if (preg_match('#%[0-9A-Z]{2}#isU', $v2) > 0) {
                        $v[$k2] = urldecode($v2);
                        $_REQUEST[$k] = $_GET[$k] = $v;
                    }
                }
            } else {
                if (preg_match('#%[0-9A-Z]{2}#isU', $v) > 0) {
                    $_GET[$k] = $_REQUEST[$k] = urldecode($v);
                }
            }
        }
    }

    /**
     * 魔术方法,获取属性,不存在时,返回Null,而不报错
     * @param $name string
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->_datas[$name])) {
            return $this->_datas[$name];
        }
        return null;
    }

    /**
     * 设置属性时,保存到私有数据中,以便下次获取
     * @param string $name
     * @param mixed $value
     */
    public function __set(string $name, $value): void
    {
        // 保存到私有数据中
        $this->_data[$name] = $value;
    }

    /**
     * 删除一个属性
     * @param string $name
     */
    public function __unset(string $name): void
    {
        // 从私有数据中清除
        unset($this->_data[$name]);
    }

    /**
     * 获取域名中的分段,可以指定下标
     * @param $offset int 下标
     * @return string|array
     */
    public function domains(int $offset = null)
    {
        // 先去除端口部分
        $d = explode(':', $_SERVER['HTTP_HOST']);

        // 分段
        $d = explode('.', $d[0]);

        // 个数
        $l = count($d);

        // CN的时候,最后一段合起来
        if ($d[$l - 1] == 'cn') {
            $d[$l - 2] .= '.cn';
            unset($d[$l - 1]);
        }

        // 反转 , 0表示 .com/.edu.cn/.net之类,1表示站点名
        $domains = array_reverse($d);

        // 不足三段,补充www
        if (!isset($domains[2])) {
            $domains[2] = 'www';
        }

        // 后面没有的补充空字符串,以免以后访问时出错
        for ($i = 3; $i < 10; $i++) {
            if (!isset($domains[$i])) {
                $domains[$i] = '';
            }
        }

        //如果要求只返回指定部分
        if (!is_null($offset)) {
            return $domains[$offset];
        }

        //返回全部
        return $domains;
    }

    /**
     * 判断浏览器语言
     * @return string
     */
    public function language(): string
    {
        if (!isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return "简体中文";
        }

        // 只取前4位，这样只判断最优先的语言。如果取前5位，可能出现en,zh的情况，影响判断。
        $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 4);

        if (preg_match("/zh-c/i", $lang)) {
            return "简体中文";
        }

        // 这个你认识吧?
        if (preg_match("/zh/i", $lang)) {
            return "繁體中文";
        }

        // 英文
        if (preg_match("/en/i", $lang)) {
            return "English";
        }

        // 法文
        if (preg_match("/fr/i", $lang)) {
            return "French";
        }

        // 德文
        if (preg_match("/de/i", $lang)) {
            return "German";
        }

        // 日语
        if (preg_match("/jp/i", $lang)) {
            return "Japanese";
        }

        // 韩文
        if (preg_match("/ko/i", $lang)) {
            return "Korean";
        }

        // 西班牙文
        if (preg_match("/es/i", $lang)) {
            return "Spanish";
        }

        // 瑞典语也是芬兰的官方语言
        if (preg_match("/sv/i", $lang)) {
            return "Swedish";
        }

        return $_SERVER["HTTP_ACCEPT_LANGUAGE"];
    }

    /**
     * 获取域名 http://xxx.yyy.zzz:ii
     * @return string
     */
    public function domain(): string
    {
        /* 协议 */
        $protocol = $this->protocol();

        /* 域名或IP地址 */
        if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
            return $protocol . $host;
        }

        if (isset($_SERVER['HTTP_HOST'])) {
            $host = $_SERVER['HTTP_HOST'];
            return $protocol . $host;
        }

        /* 端口 */
        if (isset($_SERVER['SERVER_PORT'])) {
            $port = ':' . $_SERVER['SERVER_PORT'];

            if ((':80' == $port && 'http://' == $protocol) || (':443' == $port && 'https://' == $protocol)) {
                $port = '';
            }
        } else {
            $port = '';
        }

        if (isset($_SERVER['SERVER_NAME'])) {
            $host = $_SERVER['SERVER_NAME'] . $port;
        } elseif (isset($_SERVER['SERVER_ADDR'])) {
            $host = $_SERVER['SERVER_ADDR'] . $port;
        } else {
            $host = '';
        }

        return $protocol . $host;
    }

    /**
     * 获取请求中的原始POST数据
     * @return string
     */
    public function rawPost(): string
    {
        return file_get_contents('php://input');
    }

    /**
     * 获取请示中的原始IP信息,如果通过CDN,可能是多段的
     * @return string
     */
    public function rawIp(): string
    {
        //依序检查
        foreach (['REMOTE_ADDR', 'HTTP_X_FORWARDED_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP'] as $k) {
            if (isset($_SERVER[$k]) and $_SERVER[$k]) return $_SERVER[$k];
        }

        return '0.0.0.0';
    }

    /**
     * 获取用户IP,如果通过CDN,也能获取到
     * @return string
     */
    public function ip(): string
    {
        $ip = $this->rawIp();

        $parts = explode(',', $ip);
        if ($parts) {
            return $parts[0];
        }
        return $ip;
    }

    /**
     * 获得当前的页面文件的url 不带参数
     * @return string
     */
    public function curUrl(): string
    {
        if (!empty($_SERVER['REQUEST_URI'])) {
            $nowUrls = explode('?', $_SERVER['REQUEST_URI']);
            return $nowUrls[0];
        }
        return $_SERVER['PHP_SELF'];
    }

    /**
     * 强制指定当前请求是Ajax请求,通常用在接口开发中
     */
    public function forceAjax(): void
    {
        isAjax(true);
    }

    /**
     * 判断是否由Pjax请求而来
     * @return boolean
     */
    public function isPjax(): bool
    {
        return (isset($_SERVER['HTTP_X_PJAX']) and $_SERVER['HTTP_X_PJAX']);
    }

    /**
     * 判断请求是否来自手机端
     * @return boolean
     */
    public function isMobile(): bool
    {
        // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
        if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
            return true;
        }

        // 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
        if (isset($_SERVER['HTTP_VIA'])) {
            // 找不到为false,否则为true
            return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
        }
        // 脑残法，判断手机发送的客户端标志,兼容性有待提高
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $keywords = ['android', 'wap', 'mobile', 'iphone', 'ipad', 'samsung', 'htc', 'nokia', 'sony', 'lenovo', 'sgh', 'lg', 'ericsson', 'mot', 'sharp', 'sie-', 'philips', 'panasonic', 'alcatel', 'blackberry', 'meizu', 'netfront', 'symbian', 'ucweb', 'windowsce', 'palm', 'operamini', 'operamobi', 'openwave', 'nexusone', 'cldc', 'midp'];

            // 从HTTP_USER_AGENT中查找手机浏览器的关键字
            if (preg_match("/(" . implode('|', $keywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
                return true;
            }
        }

        // 协议法，因为有可能不准确，放到最后判断
        if (isset($_SERVER['HTTP_ACCEPT'])) {
            // 如果只支持wml并且不支持html那一定是移动设备
            // 如果支持wml和html但是wml在html之前则是移动设备
            $accept = $_SERVER['HTTP_ACCEPT'] ?: '';
            if ((strpos($accept, 'vnd.wap.wml') !== false) && (strpos($accept, 'text/html') === false || (strpos($accept, 'vnd.wap.wml') < strpos($accept, 'text/html')))) {
                return true;
            }
        }
        return false;
    }

    /**
     * 判断是否IE浏览器
     * @return boolean
     */
    public function isIe(): bool
    {
        return strpos($this->agent(), 'MSIE') > 0;
    }

    /**
     * 判断用户是否使用IE6/7
     * @return boolean
     */
    public function isIe67(): bool
    {
        return strpos($this->agent(), 'MSIE 6.0') > 0 or strpos($this->agent(), 'MSIE 7.0') > 0;
    }

    /**
     * 判断 用户是否使用IE8
     * @return bool
     */
    public function isIe8(): bool
    {
        return strpos($this->agent(), 'MSIE 8.0') > 0;
    }

    /**
     * 递归检查一个数组中是否有禁止词
     * @param array $arr 要检查的数组
     * @param array $config 配置信息
     */
    private function antiHtmlArray(array $arr, array $config): void
    {
        foreach ($arr as $k => $v) {
            if (is_array($v)) {
                $this->antiHtmlArray($v, $config);
                continue;
            }

            // 如果此参数在忽略列表中,放过检查
            if (in_array($k, $config['ignoreParams'])) {
                continue;
            }

            //优先处理轻度安全参数
            if (isset($config['lightParams']) and in_array($k, $config['lightParams'])) {
                if (preg_match('/' . $config['lightDisabled'] . '/i', $v)) {
                    FileLog::instance()->antiLight($_REQUEST);
                    exit();
                }
                continue;
            }

            //其它参数,按高安全级检查
            if ($config['disabled'] and preg_match('/' . $config['disabled'] . '/i', $v)) {
                FileLog::instance()->antiHigh($_REQUEST);
                exit();
            }

            //如果参数名称非法
            if (isset($config['antiName']) and in_array($k, $config['antiName'])) {
                FileLog::instance()->antiName($_REQUEST);
                exit();
            }
        }
    }

    /**
     * 防入侵,包括IP黑名单,禁止字符,禁止词
     */
    private function antiHtml(): void
    {
        // 取防入侵配置
        $config = config('anti');

        // 如果来访IP在黑名单 中
        if (in_array($this->rawIp(), $config['blackip']) or $this->rawIp() == '-' and in_array($this->ip(), $config['blackip'])) {
            FileLog::instance()->blackIp($this->rawIp(), $this->ip(), $_REQUEST);
            exit();
        }

        if (isset($_REQUEST)) {
            $this->antiHtmlArray($_REQUEST, $config);
        }

        // 不允许 FLASH访问
        if (isset($_SERVER['HTTP_X_FLASH_VERSION']) and $_SERVER['HTTP_X_FLASH_VERSION'] and $this->ip() === $this->rawIp()) {
            FileLog::instance()->flash($this->ip(), $_REQUEST, $_SERVER);
            exit();
        }
    }

    /**
     * 获取POST参数数组
     * @return ArrayObject
     */
    public function posts(): ArrayObject
    {
        return new ArrayObject($_POST);
    }

    /**
     * 获取GET参数数组
     * @return ArrayObject
     */
    public function gets(): ArrayObject
    {
        return new ArrayObject($_GET);
    }

    /**
     * 获取Request参数数组
     * @return ArrayObject
     */
    public function requests(): ArrayObject
    {
        return new ArrayObject($_REQUEST);
    }

    /**
     * 获取请求时的URL
     * @return string
     */
    public function url(): string
    {
        return 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    /**
     * 获取引用地址
     * @return string
     */
    public function referrer(): string
    {
        return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    }

    /**
     * 返回协议名称  HTTP:// 或 HTTPS://
     * @return string
     */
    public function protocol(): string
    {
        return (isset($_SERVER['HTTPS']) and (strtolower($_SERVER['HTTPS']) != 'off')) ? 'https://' : 'http://';
    }

    /**
     * 获取当前请求的端口
     * @return string
     */
    public function port(): string
    {
        $d = explode(':', $_SERVER['HTTP_HOST']);
        return isset($d[1]) ? $d[1] : '80';
    }
}