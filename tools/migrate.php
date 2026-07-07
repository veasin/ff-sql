<?php
declare(strict_types=1);
namespace nx\tools\migrate;
require __DIR__ . '/../vendor/autoload.php';

use ff\helpers\ddl\table as ddl;
use function ff\{container, db, from, name, resource\pdo, route};

class Migrate{
	private string $projectDir;
	private string $dbDir;
	private string $migrateFile;
	private array $config = [];
	private string $currentName = 'default';
	private array $migrateData = [];
	private string $configDir = '';
	private bool $ready = false;
	private static array $i18n = [
		'require_init' => "请先执行 'php tools/migrate.php init' 创建 .migrate",
		'db_failed' => '数据库连接失败，请检查 .migrate 中的凭据',
		'init_done' => "已初始化迁移结构\n编辑 .migrate 设置数据库凭据",
		'config_not_found' => "配置 '{name}' 不存在，请先执行 config-add",
		'switched' => '已切换到配置: {name}',
		'no_configs' => '没有找到配置，请先执行 init',
		'config_exists' => "配置 '{name}' 已存在",
		'config_added' => "配置 '{name}' 已添加\n编辑 .migrate 设置凭据",
		'need_message' => '请使用 -m "message" 描述此版本',
		'head_not_set' => '本地 head 未设置，请先执行 apply',
		'head_behind' => "警告: 本地 head ({local}) 落后于 manifest head ({mhead})\n请先执行 apply 应用待处理的迁移，然后重新 save",
		'no_changes' => '没有检测到变更',
		'snapshot_saved' => '快照已保存: {file} ({count} 个表)',
		'migration_saved' => '迁移已保存: {file} ({count} 条语句)',
		'check_header' => "\n=== 迁移状态 ===\n  配置:     {config}\n  数据库: {db}\n\n",
		'no_versions' => "  尚无版本记录\n\n",
		'all_applied' => '  所有版本已应用',
		'db_error' => "  [r]数据库错误: {msg}[:]\n\n",
		'struct_match' => '  [g]结构匹配本地 head[:]',
		'uncommitted' => '  [r]未提交的变更: {count}[:]',
		'clean_hint' => "\n  执行 'php tools/migrate.php clean' 回退，或 'save -m \"msg\"' 提交",
		'no_head' => "[n]未设置本地 head — 执行 'apply' 或 'reset' 建立[:]",
		'version_not_found' => '版本 {id} 在时间线中未找到',
		'file_not_found' => '文件未找到: {file}',
		'no_head_apply' => '未设置本地 head，请先执行 apply 或 reset',
		'head_not_in_timeline' => "本地 head '{head}' 未在时间线中找到，执行 check 查看详情",
		'no_diffs' => '没有差异 — 数据库匹配本地 head ({head})',
		'diffs_header' => '差异 (数据库 vs 本地 head {head}):',
		'no_versions_timeline' => '时间线中没有版本',
		'no_versions_apply' => '没有可应用的版本',
		'target_not_found' => "目标版本 '{target}' 未在时间线中找到",
		'head_missing' => "本地 head '{head}' 未在时间线中找到\n执行 reset 从头重建",
		'no_head_reset' => '未设置本地 head，请先执行 reset',
		'already_at_target' => '已在目标版本 ({target})',
		'snapshot_in_path' => '路径中包含快照: {ids}\n请改用 reset 从快照重建',
		'no_migrations_range' => '范围内没有可应用的迁移',
		'err_file_not_found' => '  [ERROR] 文件未找到: {file}',
		'ok' => '  [OK] {id}',
		'done' => '完成，本地 head 现在: {head}',
		'failed' => '  [FAILED] {msg}',
		'no_snapshot' => '时间线中未找到快照，没有基础快照无法 reset',
		'invalid_snapshot' => '无效的快照文件',
		'drop_error' => '删除表时出错: {msg}',
		'rebuilt' => '完成，数据库已重建到 {head}',
		'no_head_clean' => '未设置本地 head，无法执行 clean',
		'head_not_in_timeline_clean' => "本地 head '{head}' 不在时间线中",
		'no_uncommitted' => '没有未提交的变更',
		'reverting' => '正在回退 {count} 个未提交的变更:',
		'cleaned' => '已清理 {count} 个未提交的变更',
		'drop_warning' => "警告: 这将删除数据库 '{database}' 中的所有表\n请输入 yes 确认: ",
		'no_tables_drop' => '没有可删除的表',
		'dropped' => "已删除 {count} 个表\n使用 reset 从头恢复",
		'need_message_baseline' => '请使用 -m "message" 描述此基线',
		'no_versions_squash' => '没有可合并的版本',
		'no_tables_squash' => '数据库中没有表，无法合并',
		'squash_done' => '基线已创建: {file}\n你可能想删除的旧版本文件:',
		'git_rm_hint' => '执行 git rm <文件> 从版本控制中移除旧文件',
		'cancelled' => '已取消',
		'proceed' => "\n是否继续? [y/N] ",
		'help' => "迁移工具\n\n用法: php tools/migrate.php <命令> [选项]\n\n命令:\n  init                     初始化 .migrate 和 db/ 结构\n  use <name>               切换活动数据库配置\n  config-list              列出所有数据库配置\n  config-add <name>        添加新数据库配置\n  save -m \"msg\" [--sql|--snap]  保存当前数据库版本\n  check                    检查数据库与本地 head 差异\n  diff [id]                显示架构差异或版本内容\n  list                     列出所有版本\n  apply [id]               应用迁移到目标版本\n  reset                    从头重建数据库\n  clean                    回滚未提交的变更\n  drop                     删除所有数据库表\n  squash -m \"msg\"          创建基线快照\n\n示例:\n  php tools/migrate.php init\n  php tools/migrate.php save -m \"add users table\"\n  php tools/migrate.php apply\n  php tools/migrate.php diff\n  php tools/migrate.php list\n",
	];
	public function __construct(string $projectDir){
		container('^i18n.migrate', self::$i18n);
		$this->projectDir = $projectDir;
		$this->dbDir = $projectDir . '/migrate';
		$this->migrateFile = $projectDir . '/.migrate';
		if(!file_exists($this->migrateFile)) return;
		$data = json_decode(file_get_contents($this->migrateFile), true);
		if(!$data) return;
		$this->migrateData = $data;
		$this->currentName = $data['current'] ?? 'default';
		$this->config = $data['configs'][$this->currentName] ?? [];
		$this->configDir = $this->dbDir . '/' . $this->currentName;
		if($this->config){
			container('#db.default', $this->config);
			$this->ready = true;
		}
	}
	private function writeJson(string $file, array $data){
		file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
	}
	private function now(): string{
		return date('Y-m-d H:i:s');
	}
	private function entryPath(array $entry): string{
		return $this->configDir . '/' . $entry['file'];
	}
	private function loadTimeline(): array{
		$file = $this->configDir . '/manifest.json';
		if(!file_exists($file)){
			$this->writeJson($file, ['timeline' => []]);
		}
		return (json_decode(file_get_contents($file), true))['timeline'] ?? [];
	}
	private function requireReady(string $command): bool{
		if(!$this->ready) return self::msg('require_init', false);
		if(!is_dir($this->configDir)) mkdir($this->configDir, 0755, true);
		return true;
	}
	private function localHead(): ?string{
		return $this->migrateData[$this->currentName]['head'] ?? null;
	}
	private function setLocalHead(string $id){
		$this->migrateData[$this->currentName]['head'] = $id;
		$this->writeJson($this->migrateFile, $this->migrateData);
	}
	private function manifestHead(array $timeline): ?string{
		return $timeline ? $timeline[array_key_last($timeline)]['id'] : null;
	}
	private function findVersionIndex(array $timeline, string $id): ?int{
		return array_find_key($timeline, fn($e) => $e['id'] === $id);
	}
	private function nextId(array $timeline): string{
		$ids = array_column($timeline, 'id');
		return str_pad((string)($ids ? max($ids) + 1 : 1), 3, '0', STR_PAD_LEFT);
	}
	private function briefFromMessage(string $msg): string{
		return mb_substr(mb_trim(preg_replace('/_+/', '_', preg_replace('/[^a-zA-Z0-9_\x{4e00}-\x{9fff}]/u', '_', $msg)), '_-'), 0, 30);
	}
	private function parseOpts(): array{
		$opts = [];
		for($i = 2, $argv = $_SERVER['argv'] ?? [], $n = count($argv); $i < $n; $i++){
			$a = $argv[$i];
			if(!str_starts_with($a, '-') || $a === '-') continue;
			$name = ltrim($a, '-');
			if(str_contains($name, '=')){
				[$k, $v] = explode('=', $name, 2);
				$opts[$k] = $v;
			}
			else $opts[$name] = ($i + 1 < $n && !str_starts_with($argv[$i + 1], '-')) ? $argv[++$i] : true;
		}
		return $opts;
	}
	private function requireDb(): bool{
		if(!$this->ready) return false;
		try{
			pdo('default');
			return true;
		}catch(\Throwable){
			return self::msg('db_failed', false);
		}
	}
	public function init(){
		if(!file_exists($this->migrateFile)){
			$this->migrateData = [
				'current' => 'default',
				'configs' => ['default' => ['dsn' => 'mysql:host=localhost;port=3306;dbname=your_db;charset=utf8mb4', 'username' => 'root', 'password' => '', 'database' => 'your_db']],
			];
			$this->writeJson($this->migrateFile, $this->migrateData);
		}
		if(!is_dir($this->dbDir)) mkdir($this->dbDir, 0755, true);
		foreach($this->migrateData['configs'] as $name => $_){
			$dir = $this->dbDir . '/' . $name;
			if(!is_dir($dir)) mkdir($dir, 0755, true);
			$mf = $dir . '/manifest.json';
			if(!file_exists($mf)) $this->writeJson($mf, ['timeline' => []]);
		}
		self::msg('init_done');
	}
	public function use(string $name){
		if(!isset($this->migrateData['configs'][$name])) return self::msg('config_not_found', ['name' => $name]);
		$this->migrateData['current'] = $name;
		$this->writeJson($this->migrateFile, $this->migrateData);
		self::msg('switched', ['name' => $name]);
	}
	public function configList(){
		$configs = $this->migrateData['configs'] ?? [];
		if(!$configs) return self::msg('no_configs');
		$current = $this->migrateData['current'] ?? '';
		foreach($configs as $name => $cfg){
			$db = $cfg['database'] ?? '';
			self::msg('  {marker} {name} ({db})', ['marker' => $name === $current ? '*' : ' ', 'name' => $name, 'db' => $db]);
		}
	}
	public function configAdd(string $name){
		if(isset($this->migrateData['configs'][$name])) return self::msg('config_exists', ['name' => $name]);
		$this->migrateData['configs'][$name] = ['dsn' => 'mysql:host=localhost;port=3306;dbname=your_db;charset=utf8mb4', 'username' => 'root', 'password' => '', 'database' => 'your_db'];
		$this->writeJson($this->migrateFile, $this->migrateData);
		$dir = $this->dbDir . '/' . $name;
		if(!is_dir($dir)) mkdir($dir, 0755, true);
		$mf = $dir . '/manifest.json';
		if(!file_exists($mf)) $this->writeJson($mf, ['timeline' => []]);
		self::msg('config_added', ['name' => $name]);
	}
	public function save(){
		if(!$this->requireReady('save') || !$this->requireDb()) return;
		$opts = $this->parseOpts();
		$message = $opts['m'] ?? $opts['message'] ?? '';
		if(!$message) return self::msg('need_message');
		$timeline = $this->loadTimeline();
		$localHead = $this->localHead();
		$mHead = $this->manifestHead($timeline);
		if($localHead === null){
			if($mHead !== null) return self::msg('head_not_set');
		}
		elseif($mHead !== null && $localHead !== $mHead){
			$localIdx = $this->findVersionIndex($timeline, $localHead);
			$mHeadIdx = $this->findVersionIndex($timeline, $mHead);
			if($localIdx !== null && $mHeadIdx !== null && $localIdx < $mHeadIdx) return self::msg('head_behind', ['local' => $localHead, 'mhead' => $mHead]);
		}
		$expected = $localHead ? $this->buildSchemaAt($localHead) : [];
		$actual = $this->loadTables();
		$diff = $this->compare($expected, $actual);
		if(!$diff) return self::msg('no_changes');
		$tables = array_unique(array_column($diff, 'table'));
		$isSnapshot = match (true) {
			(bool)($opts['sql'] ?? false) => false,
			(bool)($opts['snap'] ?? false) => true,
			default => count($tables) > 3 || count($diff) > 10,
		};
		$id = $this->nextId($timeline);
		$ext = $isSnapshot ? 'json' : 'sql';
		$filename = $id . '_' . $this->briefFromMessage($message) . '.' . $ext;
		$filepath = $this->configDir . '/' . $filename;
		if($isSnapshot){
			$this->writeJson($filepath, ['database' => $this->config['database'], 'exported_at' => $this->now(), 'tables' => $actual]);
			self::msg('snapshot_saved', ['file' => $filename, 'count' => count($actual)]);
		}
		else{
			$content = "-- Migration: $filename\n-- Date: " . $this->now() . "\n\n" . $this->diffToSql($diff);
			file_put_contents($filepath, $content);
			self::msg('migration_saved', ['file' => $filename, 'count' => count($diff)]);
		}
		$timeline[] = ['id' => $id, 'file' => $filename, 'message' => $message, 'created_at' => $this->now()];
		$this->writeJson($this->configDir . '/manifest.json', ['timeline' => $timeline]);
		$this->setLocalHead($id);
	}
	public function check(){
		if(!$this->requireReady('check') || !$this->requireDb()) return;
		$timeline = $this->loadTimeline();
		$localHead = $this->localHead();
		$mHead = $this->manifestHead($timeline);
		self::msg('check_header', ['config' => $this->currentName, 'db' => $this->config['database'] ?? '(not set)']);
		if(!$timeline){
			self::msg('no_versions');
			return;
		}
		$localIdx = $localHead !== null ? $this->findVersionIndex($timeline, $localHead) : null;
		$localLabel = $localHead ?: '(none)';
		if($localHead !== null && $localIdx === null) $localLabel = "[r]{$localHead} (not in timeline)[:]";
		self::msg('  Local head:  {label}', ['label' => $localLabel]);
		$last = $timeline[count($timeline) - 1];
		self::msg('  Latest:      {id} ({msg})', ['id' => $last['id'], 'msg' => $last['message']]);
		if($localHead !== null && $localIdx !== null){
			$mHeadIdx = count($timeline) - 1;
			if($localIdx < $mHeadIdx){
				$pending = array_slice($timeline, $localIdx + 1);
				self::msg("\n  Pending ({count}):", ['count' => count($pending)]);
				foreach($pending as $p) self::msg('    {id} ({type}) — {msg}', ['id' => $p['id'], 'type' => str_ends_with($p['file'], '.json') ? 'snapshot' : 'migration', 'msg' => $p['message']]);
				self::msg("\n  Run 'php tools/migrate.php apply' to apply");
			}
			else self::msg('all_applied');
		}
		self::msg("\n=== Structure Check ===");
		try{
			$actual = $this->loadTables();
		}catch(\Throwable $e){
			self::msg('db_error', ['msg' => $e->getMessage()]);
			return;
		}
		if($localHead !== null && $localIdx !== null){
			$diff = $this->compare($this->buildSchemaAt($localHead), $actual);
			if(!$diff) self::msg('struct_match');
			else{
				self::msg('uncommitted', ['count' => count($diff)]);
				foreach($diff as $d){
					$label = match ($d['type']) {
						'create_table' => '[r]+ TABLE[:]',
						'drop_table' => '[r]- TABLE[:]',
						'add_column', 'modify_column' => '[y]~ COL[:]',
						'drop_column' => '[r]- COL[:]',
						default => '~',
					};
					self::msg('  {label} {table}{column}', ['label' => $label, 'table' => $d['table'], 'column' => !empty($d['column']) ? ".{$d['column']}" : '']);
				}
				self::msg('clean_hint');
			}
		}
		else self::msg('no_head');
		self::msg("\n");
	}
	public function diff(?string $id = null){
		if(!$this->requireReady('diff')) return;
		$timeline = $this->loadTimeline();
		if($id !== null){
			$idx = $this->findVersionIndex($timeline, $id);
			if($idx === null) return self::msg('version_not_found', ['id' => $id]);
			$entry = $timeline[$idx];
			$filepath = $this->entryPath($entry);
			if(!file_exists($filepath)) return self::msg('file_not_found', ['file' => $entry['file']]);
			self::msg("Version {id}: {msg}\n{sep}", ['id' => $entry['id'], 'msg' => $entry['message'], 'sep' => str_repeat('-', 40)]);
			if(str_ends_with($entry['file'], '.json')){
				$data = json_decode(file_get_contents($filepath), true);
				if($data){
					foreach($data['tables'] ?? [] as $table => $schema) self::msg($this->generateCreateSql($table, $schema) . ";\n\n");
				}
			}
			else self::msg(file_get_contents($filepath));
			return;
		}
		if(!$this->requireDb()) return;
		$localHead = $this->localHead();
		if($localHead === null) return self::msg('no_head_apply');
		$idx = $this->findVersionIndex($timeline, $localHead);
		if($idx === null) return self::msg('head_not_in_timeline', ['head' => $localHead]);
		$diff = $this->compare($this->buildSchemaAt($localHead), $this->loadTables());
		if(!$diff) return self::msg('no_diffs', ['head' => $localHead]);
		self::msg('diffs_header', ['head' => $localHead]);
		self::msg(str_repeat('-', 60) . "\n" . $this->diffToSql($diff));
	}
	public function list(){
		if(!$this->requireReady('list')) return;
		$timeline = $this->loadTimeline();
		if(!$timeline) return self::msg('no_versions_timeline');
		$mHeadIdx = count($timeline) - 1;
		$localIdx = ($lh = $this->localHead()) !== null ? $this->findVersionIndex($timeline, $lh) : null;
		foreach($timeline as $i => $entry){
			$markers = [];
			if($i === $mHeadIdx) $markers[] = 'latest';
			if($localIdx !== null && $i === $localIdx) $markers[] = 'local';
			printf("%-5s %-10s %-30s %s%s\n", $entry['id'], str_ends_with($entry['file'], '.json') ? 'snapshot' : 'migration', $entry['message'], $entry['created_at'], $markers ? ' ← ' . implode(', ', $markers) : '');
		}
	}
	public function apply(?string $target = null){
		if(!$this->requireReady('apply') || !$this->requireDb()) return;
		$timeline = $this->loadTimeline();
		if(!$timeline) return self::msg('no_versions_apply');
		$mHead = $this->manifestHead($timeline);
		$targetId = $target ?? $mHead;
		$targetIdx = $this->findVersionIndex($timeline, $targetId);
		if($targetIdx === null) return self::msg('target_not_found', ['target' => $targetId]);
		$localHead = $this->localHead();
		$localIdx = $localHead !== null ? $this->findVersionIndex($timeline, $localHead) : -1;
		if($localIdx === null) return self::msg('head_missing', ['head' => $localHead]);
		if($localIdx < 0) return self::msg('no_head_reset');
		if($localIdx >= $targetIdx) return self::msg('already_at_target', ['target' => $targetId]);
		$toApply = array_slice($timeline, $localIdx + 1, $targetIdx - $localIdx);
		$snapshots = array_filter($toApply, fn($e) => str_ends_with($e['file'], '.json'));
		if($snapshots) return self::msg('snapshot_in_path', ['ids' => implode(', ', array_column($snapshots, 'id'))]);
		$sqlFiles = array_filter($toApply, fn($e) => str_ends_with($e['file'], '.sql'));
		if(!$sqlFiles) return self::msg('no_migrations_range');
		$fromEntry = $timeline[$localIdx];
		$toEntry = $timeline[$targetIdx];
		self::msg("From: {from_id} ({from_msg})\nTo:   {to_id} ({to_msg})\n\nWill execute:\n", [
			'from_id' => $fromEntry['id'],
			'from_msg' => $fromEntry['message'],
			'to_id' => $toEntry['id'],
			'to_msg' => $toEntry['message'],
		]);
		foreach($sqlFiles as $sf){
			if(!file_exists($this->entryPath($sf))) return self::msg('err_file_not_found', ['file' => $sf['file']]);
			self::msg("  + {id}: {preview}...", ['id' => $sf['id'], 'preview' => str_replace("\n", ' ', mb_substr(trim(file_get_contents($this->entryPath($sf))), 0, 80))]);
		}
		self::msg('proceed');
		if(strtolower(trim(fgets(STDIN) ?: '')) !== 'y'){
			self::msg('cancelled');
			return;
		}
		db('START TRANSACTION');
		try{
			foreach($sqlFiles as $sf){
				if(db(file_get_contents($this->entryPath($sf)), 'exec') === null) throw new \RuntimeException("Migration failed: {$sf['file']}");
				self::msg('ok', ['id' => $sf['id']]);
			}
			db('COMMIT');
			$this->setLocalHead($targetId);
			self::msg('done', ['head' => $targetId]);
		}catch(\Throwable $e){
			db('ROLLBACK');
			self::msg('failed', ['msg' => $e->getMessage()]);
		}
	}
	public function reset(){
		if(!$this->requireReady('reset') || !$this->requireDb()) return;
		$timeline = $this->loadTimeline();
		if(!$timeline) return self::msg('no_versions_timeline');
		$mHead = $this->manifestHead($timeline);
		$mHeadIdx = count($timeline) - 1;
		$snapshotEntry = null;
		$snapshotIdx = -1;
		foreach($timeline as $i => $entry){
			if(str_ends_with($entry['file'], '.json')){
				$snapshotEntry = $entry;
				$snapshotIdx = $i;
			}
		}
		if($snapshotEntry === null) return self::msg('no_snapshot');
		$sqlMigrations = array_values(array_filter(array_slice($timeline, $snapshotIdx + 1, $mHeadIdx - $snapshotIdx), fn($e) => str_ends_with($e['file'], '.sql')));
		self::msg("Will reset database '{db}':\n  Load snapshot: {sid} ({smsg})", ['db' => $this->config['database'], 'sid' => $snapshotEntry['id'], 'smsg' => $snapshotEntry['message']]);
		if($sqlMigrations) self::msg("  Apply {count} migration(s): {ids}", ['count' => count($sqlMigrations), 'ids' => implode(', ', array_column($sqlMigrations, 'id'))]);
		self::msg('proceed');
		if(strtolower(trim(fgets(STDIN) ?: '')) !== 'y'){
			self::msg('cancelled');
			return;
		}
		$snapData = json_decode(file_get_contents($this->entryPath($snapshotEntry)), true);
		if(!$snapData || !isset($snapData['tables'])) return self::msg('invalid_snapshot');
		try{
			$tables = $this->loadTables();
			if($tables){
				db('SET FOREIGN_KEY_CHECKS = 0', 'ok');
				foreach(array_keys($tables) as $t) db(new ddl($t)->drop(true), 'ok');
				db('SET FOREIGN_KEY_CHECKS = 1', 'ok');
			}
		}catch(\Throwable $e){
			return self::msg('drop_error', ['msg' => $e->getMessage()]);
		}
		db('START TRANSACTION');
		try{
			foreach($snapData['tables'] as $table => $schema){
				if(!db($this->generateCreateSql($table, $schema), 'ok')) throw new \RuntimeException("Failed to create table `$table`");
			}
			foreach($sqlMigrations as $sm){
				if(db(file_get_contents($this->entryPath($sm)), 'exec') === null) throw new \RuntimeException("Migration failed: {$sm['file']}");
			}
			db('COMMIT');
			$this->setLocalHead($mHead);
			self::msg('rebuilt', ['head' => $mHead]);
		}catch(\Throwable $e){
			db('ROLLBACK');
			self::msg('failed', ['msg' => $e->getMessage()]);
		}
	}
	public function clean(){
		if(!$this->requireReady('clean') || !$this->requireDb()) return;
		$localHead = $this->localHead();
		if($localHead === null) return self::msg('no_head_clean');
		$timeline = $this->loadTimeline();
		$idx = $this->findVersionIndex($timeline, $localHead);
		if($idx === null) return self::msg('head_not_in_timeline_clean', ['head' => $localHead]);
		$expected = $this->buildSchemaAt($localHead);
		$actual = $this->loadTables();
		$diff = $this->compare($expected, $actual);
		if(!$diff) return self::msg('no_uncommitted');
		self::msg('reverting', ['count' => count($diff)]);
		$reverseSql = '';
		$reverseAlters = [];
		foreach($diff as $d){
			$label = match ($d['type']) {
				'create_table' => "  - DROP TABLE `{$d['table']}`",
				'drop_table' => "  + CREATE TABLE `{$d['table']}`",
				'add_column' => "  - DROP COLUMN `{$d['column']}` FROM `{$d['table']}`",
				'drop_column' => "  + ADD COLUMN `{$d['column']}` TO `{$d['table']}`",
				'modify_column' => "  ~ MODIFY `{$d['table']}`.`{$d['column']}`",
				default => "  ? {$d['table']}.{$d['column']}",
			};
			self::msg('  {label}', ['label' => $label]);
			if($d['type'] === 'create_table') $reverseSql .= "DROP TABLE IF EXISTS `{$d['table']}`;\n";
			elseif($d['type'] === 'drop_table'){
				$originalSchema = $expected[$d['table']] ?? null;
				if($originalSchema) $reverseSql .= $this->generateCreateSql($d['table'], $originalSchema) . "\n";
			}
			elseif(in_array($d['type'], ['add_column', 'modify_column', 'drop_column'], true)) $reverseAlters[$d['table']][] = $d;
		}
		foreach($reverseAlters as $table => $changes){
			$add = [];
			$mod = [];
			$drop = [];
			foreach($changes as $d){
				match ($d['type']) {
					'add_column' => $drop[] = $d['column'],
					'drop_column' => $add[$d['column']] = $expected[$table]['columns'][$d['column']],
					'modify_column' => $mod[$d['column']] = $expected[$table]['columns'][$d['column']],
				};
			}
			$sql = (string)new ddl($table)->fields($add, $mod, $drop);
			if($sql) $reverseSql .= $sql . "\n";
		}
		self::msg('proceed');
		if(strtolower(trim(fgets(STDIN) ?: '')) !== 'y'){
			self::msg('cancelled');
			return;
		}
		db('START TRANSACTION');
		try{
			if(db($reverseSql, 'exec') === null) throw new \RuntimeException("Clean failed");
			db('COMMIT');
			self::msg('cleaned', ['count' => count($diff)]);
		}catch(\Throwable $e){
			db('ROLLBACK');
			self::msg('failed', ['msg' => $e->getMessage()]);
		}
	}
	public function drop(){
		if(!$this->requireReady('drop') || !$this->requireDb()) return;
		self::msg('drop_warning', ['database' => $this->config['database']]);
		if(trim(fgets(STDIN) ?: '') !== 'yes'){
			self::msg('cancelled');
			return;
		}
		try{
			$tables = $this->loadTables();
			if(!$tables) return self::msg('no_tables_drop');
			db('SET FOREIGN_KEY_CHECKS = 0', 'ok');
			$count = 0;
			foreach(array_keys($tables) as $t){
				db(new ddl($t)->drop(true), 'ok');
				$count++;
			}
			db('SET FOREIGN_KEY_CHECKS = 1', 'ok');
			self::msg('dropped', ['count' => $count]);
		}catch(\Throwable $e){
			self::msg('drop_error', ['msg' => $e->getMessage()]);
		}
	}
	public function squash(){
		if(!$this->requireReady('squash') || !$this->requireDb()) return;
		$opts = $this->parseOpts();
		$message = $opts['m'] ?? $opts['message'] ?? '';
		if(!$message) return self::msg('need_message_baseline');
		$timeline = $this->loadTimeline();
		if(!$timeline) return self::msg('no_versions_squash');
		$actual = $this->loadTables();
		if(!$actual) return self::msg('no_tables_squash');
		self::msg("Creating baseline snapshot of {count} tables...\nThis will replace {total} timeline entries with a single snapshot.", ['count' => count($actual), 'total' => count($timeline)]);
		self::msg('proceed');
		if(strtolower(trim(fgets(STDIN) ?: '')) !== 'y'){
			self::msg('cancelled');
			return;
		}
		$id = $this->nextId($timeline);
		$filename = $id . '_' . $this->briefFromMessage($message) . '.json';
		$this->writeJson($this->configDir . '/' . $filename, ['database' => $this->config['database'], 'exported_at' => $this->now(), 'tables' => $actual]);
		$this->writeJson($this->configDir . '/manifest.json', ['timeline' => [['id' => $id, 'file' => $filename, 'message' => $message, 'created_at' => $this->now()]]]);
		$this->setLocalHead($id);
		self::msg('squash_done', ['file' => $filename]);
		foreach($timeline as $entry){
			$f = $this->entryPath($entry);
			if(file_exists($f) && $f !== $this->configDir . '/' . $filename) self::msg('  {path}', ['path' => $f]);
		}
		self::msg('git_rm_hint');
	}
	// === Schema Helpers ===
	private function loadTables(): array{
		$tables = [];
		$q = pdo('default')->quote($this->config['database']);
		$rows = db("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = $q AND TABLE_TYPE = 'BASE TABLE'", 'list');
		foreach($rows as $row) $tables[$row['TABLE_NAME']] = $this->loadTableSchema($row['TABLE_NAME']);
		return $tables;
	}
	private function loadTableSchema(string $table): array{
		$columns = [];
		$q = pdo('default')->quote($this->config['database']);
		$t = pdo('default')->quote($table);
		$rows = db("SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = $q AND TABLE_NAME = $t ORDER BY ORDINAL_POSITION", 'list');
		foreach($rows as $row){
			$columns[$row['COLUMN_NAME']] = [
				'type' => $row['COLUMN_TYPE'],
				'null' => $row['IS_NULLABLE'] === 'YES',
				'default' => $row['COLUMN_DEFAULT'],
				'auto' => stripos($row['EXTRA'], 'auto_increment') !== false,
				'comment' => $row['COLUMN_COMMENT'],
			];
		}
		$indexes = [];
		$rows = db("SHOW INDEX FROM `$table`", 'list');
		foreach($rows as $row){
			$idx = $row['Key_name'];
			$indexes[$idx]['columns'][] = $row['Column_name'];
			$indexes[$idx]['type'] ??= $idx === 'PRIMARY' ? 'PRIMARY' : ($row['Non_unique'] ? 'INDEX' : 'UNIQUE');
		}
		return ['columns' => $columns, 'indexes' => $indexes];
	}
	private function parseSqlFile(string $path): array{
		$tables = ddl::fromSql(file_get_contents($path));
		$result = [];
		foreach($tables as $t){
			$schema = $t->toSchema();
			if(!$schema) continue;
			foreach($schema['columns'] as &$col) $col['null'] ??= true;
			$result[$t->name] = $schema;
		}
		return $result;
	}
	private function buildSchemaAt(string $version): array{
		$timeline = $this->loadTimeline();
		$schema = [];
		foreach($timeline as $entry){
			$fp = $this->entryPath($entry);
			if(!file_exists($fp)) continue;
			if(str_ends_with($entry['file'], '.json')){
				$data = json_decode(file_get_contents($fp), true);
				if($data && isset($data['tables'])) $schema = $data['tables'];
			}
			elseif(str_ends_with($entry['file'], '.sql')){
				foreach($this->parseSqlFile($fp) as $table => $def) $schema[$table] = $def;
			}
			if($entry['id'] === $version) return $schema;
		}
		return [];
	}
	private function findSnapshotAtOrBefore(string $version): ?array{
		$timeline = $this->loadTimeline();
		$snapshot = null;
		foreach($timeline as $entry){
			if(strcmp($entry['id'], $version) > 0) break;
			if(str_ends_with($entry['file'], '.json')) $snapshot = $entry;
		}
		return $snapshot;
	}
	// === Diff & Compare ===
	private function compare(array $old, array $new): array{
		$diff = [];
		foreach($new as $table => $schema){
			if(!isset($old[$table])) $diff[] = ['type' => 'create_table', 'table' => $table, 'schema' => $schema];
			else $diff = array_merge($diff, $this->compareTable($table, $old[$table], $schema));
		}
		foreach($old as $table => $_){
			if(!isset($new[$table])) $diff[] = ['type' => 'drop_table', 'table' => $table];
		}
		return $diff;
	}
	private function compareTable(string $table, array $old, array $new): array{
		$diff = [];
		foreach($new['columns'] as $col => $def){
			if(!isset($old['columns'][$col])) $diff[] = ['type' => 'add_column', 'table' => $table, 'column' => $col, 'def' => $def];
			elseif($this->columnChanged($old['columns'][$col], $def)) $diff[] = ['type' => 'modify_column', 'table' => $table, 'column' => $col, 'def' => $def];
		}
		foreach($old['columns'] as $col => $_){
			if(!isset($new['columns'][$col])) $diff[] = ['type' => 'drop_column', 'table' => $table, 'column' => $col];
		}
		return $diff;
	}
	private function columnChanged(array $old, array $new): bool{
		return strtoupper($old['type']) !== strtoupper($new['type']) || ($old['null'] ?? false) !== ($new['null'] ?? false) || ($old['default'] ?? null) !== ($new['default'] ?? null);
	}
	private function generateCreateSql(string $table, array $schema): string{
		return (string)new ddl($table)->create($schema['columns'], $schema['indexes'] ?? null);
	}
	private function diffToSql(array $diff): string{
		$sql = '';
		$alters = [];
		foreach($diff as $d){
			if($d['type'] === 'create_table') $sql .= (string)new ddl($d['table'])->create($d['schema']['columns'], $d['schema']['indexes'] ?? null) . "\n\n";
			elseif($d['type'] === 'drop_table') $sql .= "DROP TABLE IF EXISTS `{$d['table']}`;\n\n";
			else $alters[$d['table']][] = $d;
		}
		foreach($alters as $table => $changes){
			$add = [];
			$mod = [];
			$drop = [];
			foreach($changes as $d){
				match ($d['type']) {
					'add_column' => $add[$d['column']] = $d['def'],
					'modify_column' => $mod[$d['column']] = $d['def'],
					'drop_column' => $drop[] = $d['column'],
				};
			}
			$sql .= (string)new ddl($table)->fields($add, $mod, $drop) . "\n\n";
		}
		return $sql;
	}
	// === Helpers ===
	private static function c(string $text): string{
		static $map = [
			'[k]' => "\033[30m",
			'[r]' => "\033[31m",
			'[g]' => "\033[32m",
			'[y]' => "\033[33m",
			'[b]' => "\033[34m",
			'[m]' => "\033[35m",
			'[c]' => "\033[36m",
			'[w]' => "\033[37m",
			'[n]' => "\033[90m",
			'[:]' => "\033[0m",
		];
		return strtr($text, $map);
	}
	public static function msg($message, null|bool|array $context = [], ?bool $return = null): mixed{
		if(is_bool($context)){
			$return = $context;
			$context = [];
		}
		echo self::c(name($message, $context ?: null, null, 'i18n.migrate.'));
		return $return;
	}
}
$m = new Migrate(getcwd());
	route([
		'cli:init' => $m->init(...),
		'cli:use' => fn() => $m->use(from('1', 'params') ?? ''),
		'cli:config-list' => $m->configList(...),
		'cli:config-add' => fn() => $m->configAdd(from('1', 'params') ?? ''),
		'cli:save' => $m->save(...),
		'cli:check' => $m->check(...),
		'cli:diff' => fn() => $m->diff(from('1', 'params') ?? null),
		'cli:list' => $m->list(...),
		'cli:apply' => fn() => $m->apply(from('1', 'params') ?? null),
		'cli:reset' => $m->reset(...),
		'cli:clean' => $m->clean(...),
		'cli:drop' => $m->drop(...),
		'cli:squash' => $m->squash(...),
	]) ?? Migrate::msg('help');

