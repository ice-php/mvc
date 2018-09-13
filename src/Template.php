<?php
/**
 * 模板编译类,涉及元语言操作
 */
declare(strict_types=1);

namespace icePHP;

/*  模板语法

  模板语法
    定界符: {  }
    注意:不要有多余的空白
    同一行内避免 两个 标签,有可能会出错.

    模板中可使用以下语法 ,注意:不要有多余的空白
    {xml}
        输出XML文件头: <?xml version="1.0" encoding="utf-8" >
    {upload}
        输出上传文件根路径,通常是/upload/
    for循环
        {for($i=0;$i<count($arr);$i++)}
        {endfor} 或者 {/for}
    foreach循环
        {foreach($arr as $k=>$v)}
        {endforeach} 或者 {/foreach}
    while循环
        {while(条件表达式)}
        {endwhile} 或者 {/while}
    if判断
        {if($i>5)}
        {elseif($i>2)}
        {else}
        {endif} 或者 {/if}
    判断是否是IE浏览器
        {ifIE}
        {else}
        {endif}/{/if}
    赋值
        {assign($变量=表达式)}
        或
        {let($变量=表达式)}
    嵌套原生代码
        {php}
        {/php}
    子模板包含
    	{include('子模板',参数数组)}
    {js(脚本1,脚本2,...)}
        包含一组JS文件
    {css(样式文件1,样式文件2,...)}
        引入一组样式文件
    当变量不存在,或数组元素不存在时,显示默认值
      	{default($变量/$数组元素,'<默认值>')}
    如果存在则显示
        {?<变量或表达式>}
    显示变量的值
        {$变量}
        {$对象->属性}
        {$数组[下标]}
    模板注释
    	{#注释内容}
        {*注释内容*}
    显示函数返回值
        {date('Y-m-d H;i:s')}
        注意:既然是函数调用,最后就应该是')'结束
        例:{dump($var,'title',true)}
        这将显示一个复杂变量的值,此函数请参考相应方法
        {MUser::getName($id)}
        也可以调用Model的静态方法
    多语言翻译
        {_('文本内容')}
        这将调用全局函数translation()
    定义在viewUnit中的静态方法
        使用方法   {:方法名(参数表)}
        back($url, $title = ''):
            显示一个返回列表的按钮
        add($url, $title = ''):
            显示一个新增按钮
        crumb(array $list):
            显示面包屑,带任意多的参数,格式为名称=>地址 或 单独名称
        date($config):
            显示一个日期控件
        discount($now, $old, $pres = 1):
            计算折扣
        money($money):
            美化金额
        pathCss():
            输出样式文件根路径,通常是/static/css/ 此功能也可简写为{css}
        pathJs():
            输出脚本文件根路径,通常是/static/js/ 此功能也可简写为{js}
        pathStatic():
            输出静态资源所在路径,通常是/static/
        pathImg()
            输出图片文件根路径,通常是 /static/images/,或/static/<当前模块>/images/ 此功能也可简写为{img}
        pathRoot()
            返回根路径,去除最后的斜线
        phone($phone)
            隐藏手机号码中间三位
        seo($page, array $params = [])
            生成SEO相关内容
        short($v, $len = 20)
            对超长字符串进行截断
        string(array $config)
            显示一个不可编辑的文本
        submit($title = '提交')
            显示一个表单提交按钮
        table(array $config)
            显示一个不可编辑的表格
        ver()
            获取当前版本号
        定义的全局函数
        可以使用 {函数名(参数表)} 的形式调用全部PHP全局函数,包括框架global中定义的全局函数
        以下举例常用的全局函数
        dump($vars, $label = '', $return = false)
            显示变量内容,用于调试
        fragment($m, $c, $a, array $params = [], $cached = 7200)
            显示一个页面片段, 关于页面片段是一个专题
        left / mid / datetime/ today/json/urlAppend
            请参考相应函数
 */

final class Template
{
    //禁止实例化
    private function __construct()
    {
    }

    // 临时记录当前请求的模块名称,免得总是计算
    private static $module;

    public static function setModule(string $module): void
    {
        self::$module = $module;
    }

    //记录当前控制器
    private static $controller;

    public static function setController(string $controller): void
    {
        self::$controller = $controller;
    }

    //记录当前动作
    private static $action;

    public static function setAction(string $action): void
    {
        self::$action = $action;
    }

    //记录全部模块名称
    private static $modules;

    public static function setModules(array $modules): void
    {
        self::$modules = $modules;
    }

    //系统根目录
    private static $root;

    public static function setRoot(string $roor): void
    {
        self::$root = $roor;
    }

    /**
     * 显示视图,如果需要,智能编译
     *
     * @param string $view 视图名称
     *            格式一: <c>/<a> 这将匹配 当前模块的 view/<c>/<a>.tpl 或
     *            /module/common/view/...
     *            格式二: <a> 使用当前控制器名称,并按格式一处理
     *            格式三: null 使用当前动作名称,并按格式二处理
     * @param array $params 参数数组,数组中的键值对将被释放为变量
     * @return string
     * @throws TemplateException
     */
    static public function display(string $view = null, array $params = []): string
    {
        // 是否是被嵌套,默认是否
        static $nested = false;

        // 如果有模板变量,解开
        if ($params and count($params)) {
            extract($params);
        }

        // 如果是被嵌套,直接包含就是了
        if ($nested) {
            include(self::getTpl($view));
            return '';
        }

        // 当前是被嵌套
        $nested = 1;

        // 引入模版文件
        include(self::getTpl($view));

        // 嵌套结束了
        $nested = 0;

        return '';
    }

    /**
     * 检查所有模板文件,如需要,则编译
     * @throws TemplateException
     */
    static public function recompile(): void
    {
        //输出显示头
        echo '<div style="margin:20px;font-size:18px">';

        //逐个模板进行重编译
        foreach (self::$modules as $module) {
            // 重新编译所有视图
            self::recompileFolder($module, '');
        }

        // 清除所有页面缓存
        CacheFactory::instance('Page')->clearAll();

        //输出显示尾
        echo 'Clear Cache <br/></div>';
    }

    /**
     * 编译一个目录,如果有子目录,则递归
     * @param string $module 模块名
     * @param string $folder 目录名,相对于模板根路径 ,初始为'',之后 为'admin/',之类
     * @return int 调用层数
     * @throws TemplateException
     */
    static private function recompileFolder(string $module, string $folder): int
    {
        //本模块模板文件目录
        $path = self::$root . 'program/module/' . $module . '/view/' . $folder;

        //如果目录不存在
        if (!is_dir($path) or $folder == 'add/table') {
            return 0;
        }

        // 本目录及子目录重新编译的模板计数
        $compiled = 0;

        //遍历目录
        $root = new \DirectoryIterator($path);
        foreach ($root as $f) {
            // 不处理 . .. *.svn
            if ($f->isDot() or $f->getFilename() == '.svn') {
                continue;
            }

            //遍历子目录
            if ($f->isDir()) {
                $compiled += self::recompileFolder($module, trim($folder . '/' . $f->getFilename(), '/'));
                continue;
            }

            //只编译tpl后缀的文件
            if ($f->getExtension() != 'tpl') {
                continue;
            }

            //模板文件名
            $filename = $f->getFilename();

            //编译前文件名,编译后文件名
            $source = $path . '/' . $filename;
            $target = str_replace('/program/module/', '/run/view_c/', $path);

            echo 'Recompiled ' . $source . '<br/>';

            //编译一个模板文件
            $compiled += self::compile($source, $target);
        }

        //返回编译了多少个模板
        return $compiled;
    }

    /**
     * 根据视图获取编译后的模板文件,会自动进行智能编译
     *
     * @param string $view
     *            格式一: <c>/<a> 这将匹配 当前模块的 view/<c>/<a>.tpl 或
     *            /module/common/view/...
     *            格式二: <a> 使用当前控制器名称,并按格式一处理
     *            格式三: null 使用当前动作名称,并按格式二处理
     *
     * @return string 编译后的模板文件(带路径)
     * @throws TemplateException
     */
    static private function getTpl(?string $view = null): string
    {

        // 如果未提供视图文件名,则根据当前模块,控制器和当前动作生成
        if (!$view) {
            $view = self::$controller . '/' . self::$action;
        } elseif (strpos($view, '/') === false) {
            // 替换路径分隔符
            $view = str_replace('\\', '/', trim($view, '/'));

            // 只提供了动作名称,则附加当前控制器名称
            $view = self::$controller . '/' . $view;
        }

        //当前模块
        $m = self::$module;

        // 如果当前模块有此视图,则使用当前模块的
        if ($m and self::checkFile(self::$root . 'program/module/' . $m . '/view/' . $view . '.tpl')) {
            // 编译前的源文件
            $source = self::$root . 'program/module/' . $m . '/view/' . $view . '.tpl';

            // 编译后的目标文件
            $compiled = self::$root . 'run/view_c/' . $m . '/' . $view . '.php';
        } elseif (self::checkFile(self::$root . 'program/view/' . $view . '.tpl')) {
            // 编译前的源文件
            $source = self::$root . 'program/view/' . $view . '.tpl';

            // 编译后的目标文件
            $compiled = self::$root . 'run/view_c/' . $view . '.php';
        } else {
            // 实在没找到
            throw new TemplateException('模板文件不存在:' . $view, TemplateException::TEMPLATE_NOT_FOUND);
        }

        // 如果关闭了模板自动编译的开关,则不自动编译,这个太不常用了.
        if (configDefault(true, 'system', 'template')) {
            self::compile($source, $compiled);
        }

        // 编译后的模板应该是这个名字
        return $compiled;
    }

    /**
     * 预编译第一步
     *
     * @param $source string 模板源文件名
     * @param $target string         编译后的文件名
     * @return int 是否确实重新编译了模板文件 1/0
     * @throws TemplateException
     */
    static private function compile(string $source, string $target): int
    {
        // 目标文件已经存在,且文件日期要晚于源文件,则不用重新编译
        if (file_exists($target) and filemtime($source) <= filemtime($target)) {
            return 0;
        }

        // 重新编译
        $content = self::compile2($source);

        // 创建目标目录
        makeDir(dirname($target));

        // 写入编译后的视图
        write($target, $content, LOCK_EX);

        return 1;
    }

    /**
     * 预编译模板第二步
     *
     * @param string $view
     * @return string todo (.*?) /U非贪婪模式
     * @throws TemplateException
     */
    static private function compile2(string $view): string
    {
        // 取模板内容
        $source = file_get_contents($view);

        // 短的开始标识
        $s = '<' . '?';

        // 长的开始标识
        $b = $s . 'php ';

        // 结束标识
        $e = '?' . '>';

        // 不允许出现PHP代码
        if (strpos($source, $s) !== false) {
            throw new TemplateException('模板中不允许直接使用PHP:' . $view, TemplateException::PHP_DISABLED);
        }

        // 要替换 的内容
        $replace = [
            // 1.XML头
            '/{xml}/U' => $s . 'xml version="1.0" encoding="utf-8"' . $e,

            // 2.图片根路径
            '/{img}/U' => self::pathImg(),

            // 3.上传文件根路径
            '/{upload}/U' => self::pathUpload(),

            // 4.样式文件根路径
            '/{css}/U' => self::pathCss(),

            // 5.脚本文件根路径
            '/{js}/U' => self::pathJs(),

            // 6.通用,视图单元类的静态方法,便于开发者扩展
            '/{:(.*?\))}/U' => $s . '=icePHP\ViewUnit::\1' . $e,

            // 7.判断IE浏览器
            '/{ifIE}/' => $b . 'if(' . (self::isIE() ? '1' : '0') . '):' . $e,

            // 8.匹配 for 循环开始,与PHP语法一致
            '/{for\b(\(.*?\))}/U' => $b . 'for\1:' . $e,

            // 9.匹配 for 循环结束
            '/{endfor}/' => $b . 'endfor' . $e,
            '/{\/for}/' => $b . 'endfor' . $e,

            // 10.匹配 foreach 循环开始,与PHP语法一致
            '/{foreach\b(\(.*?\))}/U' => $b . 'foreach\1:' . $e,

            // 11.匹配 foreach 循环结束
            '/{endforeach}/' => $b . 'endforeach' . $e,
            '/{\/foreach}/' => $b . 'endforeach' . $e,

            // 12.匹配 while 循环开始
            '/{while\b(\(.*?\))}/U' => $b . 'while\1:' . $e,

            // 13.匹配 while 循环结束
            '/{endwhile}/' => $b . 'endwhile' . $e,
            '/{\/while}/' => $b . 'endwhile' . $e,

            // 14.匹配 if 开始,与PHP语法一致
            '/{if\b(\(.*?\))}/U' => $b . 'if\1:' . $e,

            // 15.匹配 elseif,与PHP语法一致
            '/{elseif\b(\(.*?\))}/U' => $b . 'elseif\1:' . $e,

            // 16.匹配 else
            '/{else}/' => $b . 'else:' . $e,

            // 17.匹配 if 结束
            '/{endif}/' => $b . 'endif' . $e,
            '/{\/if}/' => $b . 'endif' . $e,

            // 18.匹配 赋值 {assign($var=exp)}
            '/{assign\b\((.*?)\)}/U' => $b . '\1' . $e,
            '/{let\b\((.*?)\)}/U' => $b . '\1' . $e,

            // 19.匹配 原生代码开始
            '/{php}/' => $b,

            // 20.匹配 原生代码结束
            '/{\/php}/' => $e,

            // 21.匹配 子模板包含,语法 {include('子模板',参数数组)}
            '/{include\b(\(.*?\))}/U' => $b . 'icePHP\display\1' . $e,

            // 22.包含JS文件
            '/{js(\(.*?\))}/U' => $s . '=icePHP\Template::js\1' . $e,

            // 23.包含CSS文件
            '/{css(\(.*?\))}/U' => $s . '=icePHP\Template::css\1' . $e,

            // 24.default 默认值
            '/{default\((\$[^\,]*?)\,([^\)]*?)\)}/U' => "{$s}=!empty(\\1)?\\1:\\2{$e}",

            // 25. {?$xxx} 如果存在则显示
            '/{\?(\$.*?)}/U' => $s . '=(isset(\1) and \1!=="0000-00-00")?\1:""' . $e,

            // 26. 匹配 显示变量的值,与PHP语法一致
            '/{\$([^}]*?)}/U' => $s . '=$\1' . $e,

            // 27. 注释,不被编译
            '/{#[^}]*?}/U' => '',
            '/{\*[^(\*})]*?\*}/U' => '',

            // 对 url进行特殊处理
            '/{url(\(.*?\))}/U' => $s . '=icePHP\url\1' . $e,

            // 28. 匹配 显示函数返回值,与PHP语法一致
            '/{(.*?\))}/U' => $s . '=\1' . $e,

            //29.多语言翻译
            '/{\_\((.*?\))}/U' => $s . '=icePHP\translation(\1)' . $e,
        ];

        // 替换
        $target = preg_replace(array_keys($replace), array_values($replace), $source);

        // 如果不是调试模式,则去除首尾空格,去除HTML注释
        if (!isDebug()) {
            $target = preg_replace('/^\s*|\s*$/m', '', $target);
            $target = preg_replace('/<\!\-\-.*-->/m', '', $target);
        }

        // 返回编译结果
        return $target;
    }

    /**
     * 返回根路径,去除最后的斜线
     */
    static public function pathRoot(): string
    {
        return rtrim(configDefault('/', 'system', 'host'), '/\\');
    }

    /**
     * 获取本模块资源 文件目录
     */
    static private function pathModule(): string
    {
        return self::pathRoot() . '/static/' . (self::$module ? self::$module . '/' : '');
    }

    /**
     * 获取图片文件根路径
     */
    static public function pathImg(): string
    {
        return self::pathModule() . 'images/';
    }

    /**
     * 获取上传文件根路径
     */
    static private function pathUpload(): string
    {
        return self::pathRoot() . configDefault('/upload/','upload', 'path');
    }

    /**
     * 判断浏览器是否为IE
     */
    static private function isIE(): bool
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }
        return strpos($_SERVER["HTTP_USER_AGENT"], "MSIE");
    }

    /**
     * 确定一个JS文件的路径,包含时可以以开头/指定是全局脚本,否则尝试使用本模块的脚本
     *
     * @param string $file 文件名
     * @return string 路径
     */
    static private function dirCss(string $file): string
    {
        // 是否强制指定了全局
        if (substr($file, 0, 1) == '/') {
            return '/static/css' . $file . '.css';
        }

        // 当前模块
        if (self::$module) {
            // 尝试使用当前模块的
            $path = self::pathWithModule('/static', "css/{$file}.css");
            if (self::checkFile(self::$root . '/public' . $path)) {
                return $path;
            }
        }

        // 使用全局
        return '/static/css/' . $file . '.css';
    }

    /**
     * 拼接多个文件名,用于构造合并后的缓存文件名
     *
     * @param array $names 文件名数组
     * @return string 合并后的名字
     */
    static private function cacheBind(array $names): string
    {
        return md5(json($names));
    }

    /**
     * 在模板中包含样式文件
     * 可变参数: 样式文件名称列表
     * @param $files string[]
     * @return string
     */
    static public function css(string ... $files): string
    {
        // 如果没有参数,则忽略
        if (count($files) < 1) {
            return '';
        }

        // 如果只有一个参数,则直接包含
        if (count($files) == 1) {
            // 构造CSS文件的URL
            $url = self::pathRoot() . self::dirCss($files[0]);

            // 调试模式下,加上一个版本号
            if (isDebug()) {
                $url .= '?v=' . configDefault('0','system', 'version');
            }

            // 返回HTML包含CSS的语法
            return "<link rel='stylesheet' type='text/css' href='$url' media='all'/>\n";
        }

        //构造缓存文件名
        $name = self::cacheBind($files);

        //缓存文件相对路径,相对public,中间以模块分隔
        $cachePath = self::pathWithModule('/static', "cache/{$name}.css");

        //缓存文件物理目录
        $cacheDir = self::$root . '/public' . $cachePath;

        // 如果合并文件不存在,则生成
        if (isDebug() or !self::checkFile($cacheDir)) {
            // 脚本内容
            $content = '';

            // 逐个脚本内容取出
            foreach (func_get_args() as $arg) {
                $fullName = self::$root . 'public' . self::dirCss($arg);

                $content .= "\r\n\r\n<!--{$fullName}-->\r\n";
                // 内容合并
                $content .= file_get_contents($fullName) . "\r\n";
            }

            // 创建输出路径
            makeDir($cacheDir);

            // 写入缓存文件
            write($cacheDir, $content);
        }

        // 返回包含这合并文件的脚本语句

        $url = self::pathRoot() . $cachePath;
        return "<link rel='stylesheet' type='text/css' href='$url'  media='all'/>\n";
    }

    /**
     * 拼接指定 模块的路径
     *
     * @param string $prefix 前缀
     * @param string $suffix 后缀
     * @return string 组合后的路径
     */
    static private function pathWithModule(string $prefix, string $suffix): string
    {
        $module = self::$module;

        if ($module) {
            return rtrim($prefix, '/') . '/' . $module . '/' . ltrim($suffix, '/');
        }
        return rtrim($prefix, '/') . '/' . ltrim($suffix, '/');
    }

    /**
     * 确定一个JS文件的相对Public目录的相对路径,包含时可以以开头/指定是全局脚本,否则尝试使用本模块的脚本
     *
     * @param string $file 文件名
     * @return string 路径
     */
    static private function dirJs(string $file): string
    {
        // 是否强制指定了全局脚本
        if (substr($file, 0, 1) == '/') {
            return '/static/js' . $file . '.js';
        }

        // 当前模块
        if (self::$module) {
            // 尝试使用当前模块的脚本
            $path = self::pathWithModule('/static', "js/{$file}.js");
            if (self::checkFile(self::$root . '/public' . $path)) {
                return $path;
            }
        }

        // 使用全局脚本
        return '/static/js/' . $file . '.js';
    }

    /**
     * 在模板中包含脚本文件
     * 可变参数: 脚本文件名称列表
     * @param $files string[]
     * @return string
     */
    static public function js(string ...$files): string
    {
        // 如果没有参数,则忽略
        if (count($files) < 1) {
            return '';
        }

        // 只有一个参数,则包含原来的文件
        if (count($files) == 1) {
            // 构造JS文件的URL
            $url = self::pathRoot() . self::dirJs($files[0]);

            // 调试模式,加上版本号
            if (isDebug()) {
                $url .= '?v=' . configDefault('0','system', 'version');
            }

            // 构造 HTML中的JS包含语法
            return "<script type='text/javascript' src='$url'></script>\n";
        }

        // 文件名拼接
        $cacheName = self::cacheBind($files);
        $cachePath = self::pathWithModule('/static', "cache/{$cacheName}.js");
        $cacheDir = self::$root . '/public' . $cachePath;

        // 如果合并文件不存在,则生成
        if (isDebug() or !self::checkFile($cacheDir)) {
            // 脚本内容
            $content = '';

            // 逐个脚本内容取出
            foreach (func_get_args() as $arg) {
                $dirJs = self::dirJs($arg);

                //脚本文件的全路径
                $fullName = self::$root . 'public' . $dirJs;

                //加一个注释
                $content .= "\r\n\r\n{$dirJs}\r\n";

                // 内容合并
                $content .= file_get_contents($fullName) . "\n";
            }

            // 创建输出路径
            makeDir(dirname($cacheDir));

            // 写入缓存文件
            write($cacheDir, $content);
        }

        $url = self::pathRoot() . $cachePath;
        return "<script type='text/javascript' src='$url'></script>\n";
    }

    /**
     * 简单模板替换
     * @param $template string 模板源
     * @param array $params 替换变量值 对
     * @return string 替换后的模板
     */
    static public function replace(string $template, array $params = []): string
    {
        foreach ($params as $k => $v) {
            $template = str_replace('{$' . $k . '}', $v, $template);
        }
        return $template;
    }

    /**
     * 取脚本文件路径
     */
    static public function pathJs(): string
    {
        return self::pathModule() . 'js/';
    }

    /**
     * 取所有静态资源所在路径
     */
    static public function pathStatic(): string
    {
        return self::pathRoot() . '/static/';
    }

    /**
     * 取样式表文件路径
     */
    static public function pathCss(): string
    {
        return self::pathModule() . 'css/';
    }


    /**
     * 判断文件是否存在
     *
     * @param $filename string 文件名及路径
     * @return bool
     */
    private static function checkFile(string $filename): bool
    {
        return file_exists($filename) and is_file($filename) and basename($filename) === basename(realpath($filename));
    }

}