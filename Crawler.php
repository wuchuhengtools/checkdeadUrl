<?php 

/**
 * 网站死链检测程序
 *
 * @filename  Crawler.php
 * @author wuchuehng<wuchuheng@163.com>
 * @date   2019/07/26
 */

declare(strict_types=1); //强类型模式
if(strpos(strtolower(PHP_OS), 'win') === 0) exit("not support windows, please be run on Linux\n");
if(!extension_loaded('pcntl')) exit("Please install pcntl extension.\n");
if (substr(php_sapi_name(), 0, 3) !== 'cli') die("This Programe can only be run in CLI mode");
if(!extension_loaded('Redis')) exit("Please install Redis extension.\n");

use Symfony\Component\DomCrawler\Crawler as DomCrawler;

require_once "./vendor/autoload.php";
class Crawler
{
    public  static $count =  10; //进程量
    public  static $domain=  'http://qqhzpjmw.com'; //网站主页
    private static $Redis;
    private static $redis_pass = '';
    private static $redis_host = '127.0.0.1';
    private static $redis_port = 6379;


    /**
     * 获取redis连接实例
     *
     * @return redis连接对象
     */
    private static function getRedisInstance() : object
    {
        if (!is_object(self::$Redis)) {
            $Redis = new \Redis();
            $Redis->connect(self::$redis_host, self::$redis_port);
            self::$Redis = $Redis;
        }
        return self::$Redis; 
    }


    /**
     *   检测页面是否有死链并入队新的url
     *
     *   @url    
     */
    private static function checkUrl(string $url)
    {
        $current_url = $url;
        $Redis = self::getRedisInstance();
        if($Redis->hExists('beCrawler', $url)) return;
        $ch = curl_init();  
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $html = curl_exec($ch);
        curl_close($ch);
        $crawler = new DomCrawler($html); 
        $urls = $crawler->filterXPath('//a/@href')
            ->each(function (DomCrawler $node, $i ) {
                return $node->text();
            });
        foreach($urls as $url) {
            $purl = parse_url($url);  
            $pdurl = parse_url(self::$domain);
            if(!array_key_exists('host', $purl)) {
                $url = self::$domain . $url;
            }
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_NOBODY, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code === 200 && !$Redis->hExists('beCrawler', $url)) {
                //加入队列
                $Redis->lPush(
                    'waitting_queue',
                    json_encode([
                        'url'=>$url, 
                        'micotime'=>microtime(true),
                        'pid'=>posix_getpid()])
                );
            } elseif($code !== 200) {
                $deal_url = [];
                if ($Redis->hExists('dealUrl', $current_url)) {
                    $deal_url = json_decode(
                        $Redis->hGet('dealUrl', $current_url)
                    );
                }
                $deal_url[] = $url;
                $deal_url = json_encode(array_filter($deal_url));
                $Redis->hset('dealUrl', $current_url, $deal_url);
            }
        }
        $Redis->hset('beCrawler', $current_url, json_encode(['micotime'=>microtime(true),'pid'=>posix_getpid()]));
    }


    /**
     * 启动所有进程
     *
     *
     */
    public static function runAll()
    {
        for ($i = 0; $i < self::$count; $i ++) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                exit("fork progresses error\n");
            } else if($pid == 0) {
                 cli_set_process_title('subprocess PID:' . posix_getpid());
                 $Redis = self::getRedisInstance();
                 if  ($Redis->lLen('waitting_queue') === 0  && !$Redis->hExists('beCrawler', self::$domain)){
                     self::checkUrl(self::$domain);
                 } 
                 while($Redis->lLen('waitting_queue') > 0 )
                 {
                   $url = json_decode($Redis->lPop('waitting_queue'), true);
                   $url = $url['url'];
                   self::checkUrl($url);
                 }
                 exit(0); //中断子进程重复fork
            } else {
                // ...
            }
        }
        cli_set_process_title('main Crawler');
        //主进程
        $pid = pcntl_wait($status, WUNTRACED); //取得子进程结束状态
        if (pcntl_wifexited($status)) {
            if ($Redis->lLen('waitting_queue')  !== 0) {
                 //补充意外死掉的进程 
                 self::$count = 1;
                 self::runAll();
            }
            echo "\n\n* Sub process: {$pid} exited with {$status}";
        } 
    }


   /**
     *   
     *
     *
     */ 

}
Crawler::runAll();
for( $i = 1; $i <= 3 ; $i++ ){
        }

