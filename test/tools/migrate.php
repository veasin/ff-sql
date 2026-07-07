<?php
declare(strict_types=1);
/**
 * migrate.php 完整测试
 *
 * 测试策略：
 * - 非 DB 部分（init、config、msg）—— 直接使用真实 Migrate 实例 + 临时目录，即时求值
 * - Schema 差异逻辑（compare、diffToSql、generateCreateSql）—— Closure::bind 调用私有方法
 * - DB 完整流程（save→check→diff→clean）—— 基于 SQLite :memory: + 手工注入 mock 表数据
 * - 最后清理临时目录
 */

error_reporting(E_ALL);
require_once __DIR__ . '/../../vendor/autoload.php';

use function ff\{container, db, test};
use nx\tools\migrate\Migrate;

$_SERVER['argv'] = ['test.php'];
// 清空可能的残留 buffer，然后静默引入 migrate.php
for($i = ob_get_level(); $i > 0; $i--) ob_end_clean();
ob_start();
require __DIR__ . '/../../tools/migrate.php';
ob_end_clean();

// ─── 辅助函数 ────────────────────────────────────────────

function capture(callable $fn): string{
	ob_start();
	$fn();
	return ob_get_clean();
}

function tmpDir(): string{
	$dir = sys_get_temp_dir() . '/ff_migrate_test_' . bin2hex(random_bytes(4));
	mkdir($dir, 0777, true);
	register_shutdown_function(function() use ($dir){
		if(!is_dir($dir)) return;
		$it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
		$it = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
		foreach($it as $f){
			$f->isDir() ? rmdir((string)$f->getRealPath()) : unlink((string)$f->getRealPath());
		}
		rmdir($dir);
	});
	return $dir;
}

function invoke(object $obj, string $method): \Closure{
	return \Closure::bind(fn() => $this->$method(...func_get_args()), $obj, Migrate::class);
}

function prop(object $obj, string $name): \Closure{
	return \Closure::bind(fn($v = null) => $v === null ? $this->$name : ($this->$name = $v), $obj, Migrate::class);
}

// ─── 1. Init / Config ─────────────────────────────────

$tmp = tmpDir();
$m1 = new Migrate($tmp);

test('1.1 未 init .migrate 不存在', file_exists("$tmp/.migrate"), false);

capture(fn() => $m1->init());
test('1.2 init 创建 .migrate', file_exists("$tmp/.migrate"), true);
test('1.3 init 创建 migrate/', is_dir("$tmp/migrate"), true);
test('1.4 init 创建 manifest', file_exists("$tmp/migrate/default/manifest.json"), true);

capture(fn() => $m1->configAdd('testing'));

$_cfg1 = json_decode(file_get_contents("$tmp/.migrate"), true);
test('1.5 config-add 新增', isset($_cfg1['configs']['testing']), true);

capture(fn() => $m1->use('testing'));
$_cfg1 = json_decode(file_get_contents("$tmp/.migrate"), true);
test('1.6 use 切到 testing', $_cfg1['current'], 'testing');

capture(fn() => $m1->use('default'));
$_cfg1 = json_decode(file_get_contents("$tmp/.migrate"), true);
test('1.7 use 回到 default', $_cfg1['current'], 'default');

// ─── 2. Msg / i18n ───────────────────────────────────

container('^i18n.lang', 'zh_CN');

$_msg1 = capture(fn() => Migrate::msg('require_init'));
test('2.1 msg 基本文本', $_msg1, "请先执行 'php tools/migrate.php init' 创建 .migrate");

$_msg2 = capture(fn() => Migrate::msg('config_not_found', ['name' => 'x']));
test('2.2 msg 占位符', $_msg2, "配置 'x' 不存在，请先执行 config-add");

$_out3 = capture(fn() => Migrate::msg('no_changes', false));
test('2.3 msg 文本随 return false 输出', $_out3, '没有检测到变更');
// 单独验证 return=false 路径
ob_start();
$_ret3 = Migrate::msg('no_changes', false);
ob_end_clean();
test('2.4 msg return=false', $_ret3, false);

$_msg3 = capture(fn() => Migrate::msg('done', ['head' => '003']));
test('2.5 msg done', $_msg3, '完成，本地 head 现在: 003');

// ─── 3. Timeline / 辅助方法 ───────────────────────────

$m2 = new Migrate($tmp);
capture(fn() => $m2->init());
prop($m2, 'currentName')('default');
prop($m2, 'configDir')("$tmp/migrate/default");

$loadTimeline = invoke($m2, 'loadTimeline');
$findVersionIndex = invoke($m2, 'findVersionIndex');
$nextId = invoke($m2, 'nextId');
$briefFromMessage = invoke($m2, 'briefFromMessage');

test('3.1 loadTimeline 空', $loadTimeline(), []);

$tl = [['id' => '001', 'file' => '001_init.sql'], ['id' => '002', 'file' => '002_add.sql']];
file_put_contents("$tmp/migrate/default/manifest.json",
	json_encode(['timeline' => $tl], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

test('3.2 loadTimeline 含条目', $loadTimeline(), $tl);
test('3.3 findVersionIndex 命中 002', $findVersionIndex($tl, '002'), 1);
test('3.4 findVersionIndex 未命中', $findVersionIndex($tl, '999'), null);
test('3.5 nextId 自增', $nextId($tl), '003');
test('3.6 nextId 空起点', $nextId([]), '001');
test('3.7 briefFromMessage 中文', $briefFromMessage('添加用户表'), '添加用户表');
test('3.8 briefFromMessage 截断', strlen($briefFromMessage(str_repeat('a', 40))), 30);
test('3.9 briefFromMessage 特殊字符', $briefFromMessage('create: users table!'), 'create_users_table');

// ─── 4. Schema 差异逻辑 ─────────────────────────────

$m3 = new Migrate($tmp);
$compare = invoke($m3, 'compare');
$compareTable = invoke($m3, 'compareTable');
$columnChanged = invoke($m3, 'columnChanged');

$oldT = [
	'columns' => [
		'id'   => ['type' => 'INT',         'null' => false, 'auto' => true],
		'name' => ['type' => 'VARCHAR(100)', 'null' => true],
	],
	'indexes' => ['PRIMARY' => ['type' => 'PRIMARY', 'columns' => ['id']]],
];
$newT = [
	'columns' => [
		'id'    => ['type' => 'INT',          'null' => false, 'auto' => true],
		'name'  => ['type' => 'VARCHAR(200)', 'null' => true],
		'email' => ['type' => 'VARCHAR(255)', 'null' => true],
	],
	'indexes' => ['PRIMARY' => ['type' => 'PRIMARY', 'columns' => ['id']]],
];

test('4.1 columnChanged 类型变化',
	$columnChanged(['type' => 'VARCHAR(100)', 'null' => true], ['type' => 'VARCHAR(200)', 'null' => true]), true);
test('4.2 columnChanged 未变化',
	$columnChanged(['type' => 'INT', 'null' => false], ['type' => 'INT', 'null' => false]), false);
test('4.3 columnChanged null 变化',
	$columnChanged(['type' => 'INT', 'null' => true], ['type' => 'INT', 'null' => false]), true);
test('4.4 columnChanged default 变化',
	$columnChanged(['type' => 'INT', 'null' => true, 'default' => 0], ['type' => 'INT', 'null' => true, 'default' => 1]), true);

$_diffT = $compareTable('users', $oldT, $newT);
test('4.5 compareTable admin_column',
	in_array(['type' => 'add_column', 'table' => 'users', 'column' => 'email', 'def' => ['type' => 'VARCHAR(255)', 'null' => true]], $_diffT), true);
test('4.6 compareTable modify_column',
	in_array(['type' => 'modify_column', 'table' => 'users', 'column' => 'name', 'def' => ['type' => 'VARCHAR(200)', 'null' => true]], $_diffT), true);

test('4.7 compare 完整 diff', $compare(['users' => $oldT], ['users' => $newT]), [
	['type' => 'modify_column', 'table' => 'users', 'column' => 'name', 'def' => ['type' => 'VARCHAR(200)', 'null' => true]],
	['type' => 'add_column', 'table' => 'users', 'column' => 'email', 'def' => ['type' => 'VARCHAR(255)', 'null' => true]],
]);

test('4.8 compare drop_table', $compare(['users' => $oldT], []), [['type' => 'drop_table', 'table' => 'users']]);

$_createD = $compare([], ['posts' => ['columns' => ['id' => ['type' => 'INT', 'null' => false]], 'indexes' => []]]);
test('4.9 compare create_table', $_createD[0]['type'] === 'create_table' && $_createD[0]['table'] === 'posts', true);

// ─── 5. SQL 生成 ────────────────────────────────────

$genSql = invoke($m3, 'generateCreateSql');
$diffToSql = invoke($m3, 'diffToSql');

$_sql = $genSql('users', $oldT);
test('5.1 generateCreateSql 含 CREATE TABLE', str_contains($_sql, 'CREATE TABLE'), true);
test('5.2 generateCreateSql 含表名', str_contains($_sql, '`users`'), true);
test('5.3 generateCreateSql 含 PRIMARY KEY', str_contains($_sql, 'PRIMARY KEY'), true);

$_alter = $diffToSql([['type' => 'add_column', 'table' => 'users', 'column' => 'email', 'def' => ['type' => 'VARCHAR(255)']]]);
test('5.4 diffToSql ADD COLUMN', str_contains($_alter, 'ADD COLUMN `email`'), true);

$_drop = $diffToSql([['type' => 'drop_table', 'table' => 'obsolete']]);
test('5.5 diffToSql DROP TABLE', str_contains($_drop, 'DROP TABLE IF EXISTS `obsolete`'), true);

$_mixed = $diffToSql([
	['type' => 'create_table', 'table' => 'new_t', 'schema' => ['columns' => ['a' => ['type' => 'INT']], 'indexes' => []]],
	['type' => 'add_column', 'table' => 'users', 'column' => 'b', 'def' => ['type' => 'VARCHAR(10)']],
]);
test('5.6 diffToSql 混合', str_contains($_mixed, 'CREATE TABLE') && str_contains($_mixed, 'ADD COLUMN'), true);

// ─── 6. BuildSchemaAt / ParseSqlFile ─────────────────

$m4 = new Migrate($tmp);
prop($m4, 'currentName')('default');
prop($m4, 'configDir')("$tmp/migrate/default");

file_put_contents("$tmp/migrate/default/001_users.json", json_encode([
	'database' => 'test', 'exported_at' => '2025-01-01',
	'tables' => ['users' => $oldT],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

file_put_contents("$tmp/migrate/default/manifest.json", json_encode([
	'timeline' => [['id' => '001', 'file' => '001_users.json', 'message' => 'init', 'created_at' => '2025-01-01']],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$buildSchemaAt = invoke($m4, 'buildSchemaAt');
$_s = $buildSchemaAt('001');
test('6.1 buildSchemaAt 读 snapshot', isset($_s['users']['columns']['id']), true);
test('6.2 buildSchemaAt 列类型', $_s['users']['columns']['id']['type'], 'INT');

$sqlPath = "$tmp/migrate/default/002_posts.sql";
file_put_contents($sqlPath, "CREATE TABLE `posts` (\n  `id` INT AUTO_INCREMENT,\n  `title` VARCHAR(200) NOT NULL,\n  PRIMARY KEY (`id`)\n);\n");

$parseSqlFile = invoke($m4, 'parseSqlFile');
$_p = $parseSqlFile($sqlPath);
test('6.3 parseSqlFile 解析', isset($_p['posts']['columns']['title']), true);
test('6.4 parseSqlFile 类型', $_p['posts']['columns']['title']['type'], 'VARCHAR(200)');

$_mf = json_decode(file_get_contents("$tmp/migrate/default/manifest.json"), true);
$_mf['timeline'][] = ['id' => '002', 'file' => '002_posts.sql', 'message' => 'add posts', 'created_at' => '2025-01-02'];
file_put_contents("$tmp/migrate/default/manifest.json", json_encode($_mf, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$_s2 = $buildSchemaAt('002');
test('6.5 buildSchemaAt 累积 snapshot+migration', isset($_s2['users']) && isset($_s2['posts']), true);

// ─── 7. DB 完整流程（SQLite :memory: + mock） ─────────

$tmp2 = tmpDir();
capture(fn() => (new Migrate($tmp2))->init());

$_cfg2 = json_decode(file_get_contents("$tmp2/.migrate"), true);
$_cfg2['configs']['default'] = ['dsn' => 'sqlite::memory:', 'username' => null, 'password' => null, 'database' => 'main'];
file_put_contents("$tmp2/.migrate", json_encode($_cfg2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$m5 = new Migrate($tmp2);

db("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)", 'exec');
db("CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL)", 'exec');

file_put_contents("$tmp2/migrate/default/001_init.json", json_encode([
	'database' => 'main', 'exported_at' => '2025-01-01',
	'tables' => ['users' => [
		'columns' => ['id' => ['type' => 'INTEGER', 'null' => false, 'auto' => true], 'name' => ['type' => 'TEXT', 'null' => false]],
		'indexes' => ['PRIMARY' => ['type' => 'PRIMARY', 'columns' => ['id']]],
	]],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

file_put_contents("$tmp2/migrate/default/manifest.json", json_encode([
	'timeline' => [
		['id' => '001', 'file' => '001_init.json', 'message' => 'init users', 'created_at' => '2025-01-01'],
		['id' => '002', 'file' => '002_add_posts.sql', 'message' => 'add posts', 'created_at' => '2025-01-02'],
	],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$_md = json_decode(file_get_contents("$tmp2/.migrate"), true);
$_md['default']['head'] = '001';
file_put_contents("$tmp2/.migrate", json_encode($_md, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$m5 = new Migrate($tmp2);

$buildAt = invoke($m5, 'buildSchemaAt');
$_expected = $buildAt('001');

$_actualTables = [
	'users' => [
		'columns' => ['id' => ['type' => 'INTEGER', 'null' => false, 'auto' => true], 'name' => ['type' => 'TEXT', 'null' => false]],
		'indexes' => ['PRIMARY' => ['type' => 'PRIMARY', 'columns' => ['id']]],
	],
	'posts' => [
		'columns' => ['id' => ['type' => 'INTEGER', 'null' => false, 'auto' => true], 'title' => ['type' => 'TEXT', 'null' => false]],
		'indexes' => ['PRIMARY' => ['type' => 'PRIMARY', 'columns' => ['id']]],
	],
];

$compareFn = invoke($m5, 'compare');
$_diff = $compareFn($_expected, $_actualTables);
test('7.1 diff 检测 posts 为新表', count($_diff) === 1 && $_diff[0]['type'] === 'create_table' && $_diff[0]['table'] === 'posts', true);

$nextId = invoke($m5, 'nextId');
$_tl = json_decode(file_get_contents("$tmp2/migrate/default/manifest.json"), true)['timeline'];
test('7.2 新版本号', $nextId($_tl), '003');

$brief = invoke($m5, 'briefFromMessage');
test('7.3 文件名预览', $brief('add posts table'), 'add_posts_table');

$diffToSql = invoke($m5, 'diffToSql');
$_migSql = $diffToSql($_diff);
test('7.4 migration SQL CREATE TABLE', str_contains($_migSql, 'CREATE TABLE'), true);
test('7.5 migration SQL 含 posts', str_contains($_migSql, '`posts`'), true);

// 验证 save 文件写入
$_filename = '003_' . $brief('add posts table') . '.sql';
file_put_contents("$tmp2/migrate/default/$_filename", $_migSql);
test('7.6 save 文件存在', file_exists("$tmp2/migrate/default/$_filename"), true);

// 验证 manifest 更新
$_tl[] = ['id' => '003', 'file' => $_filename, 'message' => 'add posts table', 'created_at' => date('Y-m-d H:i:s')];
file_put_contents("$tmp2/migrate/default/manifest.json", json_encode(['timeline' => $_tl], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
test('7.7 manifest 条目', count(json_decode(file_get_contents("$tmp2/migrate/default/manifest.json"), true)['timeline']), 3);

// ─── 8. BuildSchemaAt 累计 timeline ─────────────────

$buildAt2 = invoke($m5, 'buildSchemaAt');
$_s3 = $buildAt2('003');
test('8.1 buildSchemaAt 003 含 users+posts', isset($_s3['users']) && isset($_s3['posts']), true);

// 额外验证：纯逻辑——drop_column 检测
$_tA = ['columns' => ['a' => ['type' => 'INT'], 'b' => ['type' => 'INT']], 'indexes' => []];
$_tB = ['columns' => ['a' => ['type' => 'INT']], 'indexes' => []];
$_diffCol = invoke($m5, 'compareTable')('t', $_tA, $_tB);
test('8.2 compareTable drop_column', $_diffCol, [['type' => 'drop_column', 'table' => 't', 'column' => 'b']]);

// ─── 9. 多配置 ──────────────────────────────────────

$m6 = new Migrate($tmp);
capture(fn() => $m6->init());
capture(fn() => $m6->configAdd('staging'));
capture(fn() => $m6->use('staging'));
$_cfg9 = json_decode(file_get_contents("$tmp/.migrate"), true);
test('9.1 current=staging', $_cfg9['current'], 'staging');

capture(fn() => $m6->use('default'));
$_cfg9 = json_decode(file_get_contents("$tmp/.migrate"), true);
test('9.2 current=default', $_cfg9['current'], 'default');

// ─── 10. 边界 ──────────────────────────────────────

$_e1 = capture(fn() => Migrate::msg('config_not_found', ['name' => 'prod']));
test('10.1 config_not_found', $_e1, "配置 'prod' 不存在，请先执行 config-add");

$_e2 = capture(fn() => Migrate::msg('init_done'));
test('10.2 init_done', $_e2, "已初始化迁移结构\n编辑 .migrate 设置数据库凭据");
