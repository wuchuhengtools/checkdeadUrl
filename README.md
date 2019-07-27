####  网站死链检测脚本

####安装：
    * 依赖： `redis`服务; `php` >  7.0 ;`php`的`redis`, `pnclt`, `poxis`, `curl` 扩展。`composer` 
    * 修改:  切换到项目根目录后,打开`Crawler.php`,在`public static $domain =  <要检测的网址>;` 以及`redis`连接配置。
    * 启动 `composer install && php  Crawler.php `

#### redis保存采集的结果
    `beCrawler`哈希表保存已经爬取过的页面链接。
    `dealUrl` 哈希表保存已经发现死链的页面链接，`field`为那个页面，`value`为那个页面
    发现的死链（`json`序列）。
    `waitting_queue`(`json`序列)列表保存要检查的页面。

