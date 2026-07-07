## 配置文件 `.migrate`

数据库凭据和迁移状态存储在项目根目录的 `.migrate` 文件中（JSON 格式，**不提交**到版本库）。

```json
{
  "current": "default",
  "configs": {
    "default": {
      "dsn": "mysql:host=localhost;port=3306;dbname=myapp;charset=utf8mb4",
      "username": "root",
      "password": "",
      "database": "myapp"
    }
  },
  "default": {
    "head": "003"
  }
}
```

- `current` — 当前激活的配置名
- `configs` — 多套数据库配置，通过 `use <name>` 切换
- `{配置名}.head` — 本地 head 指针，记录当前数据库对应的 timeline 版本

## 目录结构

```
your-project/
├── .migrate                  # 凭据 + head 状态，gitignored
├── migrate/                  # 提交到版本库
│   ├── default/              # 默认配置的迁移文件
│   │   ├── manifest.json     # timeline 清单
│   │   ├── 001_init.json     # 结构快照
│   │   ├── 002_add_posts.sql # 增量迁移
│   │   └── 003_add_index.sql
│   └── testing/              # testing 配置的迁移文件
│       └── manifest.json
├── vendor/
├── composer.json
└── tools/
    └── migrate.php
```

- `migrate/<配置名>/manifest.json` — timeline 清单，记录所有版本
- `.sql` 文件 — 增量迁移（ALTER TABLE 等）
- `.json` 文件 — 结构快照（完整表结构，含所有列和索引定义）

## 核心概念

### Timeline

每次 `save` 在 manifest.json 的 `timeline[]` 中追加一条记录：

```json
{"id": "001", "file": "001_init.json", "message": "initial schema", "created_at": "2025-01-01"}
```

版本号 `id` 自动递增（001, 002, …）。

### Snapshot vs Migration

| 类型 | 扩展名 | 内容 | 触发条件 |
|------|--------|------|----------|
| Snapshot | `.json` | 完整表结构 | 变更涉及 >3 个表 或 >10 条 DDL |
| Migration | `.sql` | 增量 DDL 语句 | 变更范围小，或 `--sql` 显式指定 |

### Local Head

本地 head 记录当前数据库实际处于 timeline 的哪个版本。`check` 通过比较 head 对应结构与数据库实际结构来检测未提交的变更。

## 命令参考

| 命令 | 说明 |
|------|------|
| `init` | 生成 `.migrate` 模板和 `migrate/` 目录 |
| `use <name>` | 切换当前数据库配置 |
| `config-list` | 列出所有数据库配置 |
| `config-add <name>` | 新增数据库配置 |
| `save -m "msg" [--sql\|--snap]` | 保存当前数据库状态的版本 |
| `check` | 检查数据库与本地 head 的差异 |
| `diff [id]` | 显示差异 DDL，或查看指定版本内容 |
| `list` | 列出 timeline 所有版本 |
| `apply [id]` | 应用迁移到目标版本 |
| `reset` | 从最近快照重建数据库 |
| `clean` | 回滚未提交的变更 |
| `drop` | 删除数据库所有表 |
| `squash -m "msg"` | 将 timeline 合并为单个基线快照 |

## 工作流

### 新项目

```bash
# 1. 初始化
php tools/migrate.php init
# 编辑 .migrate 填写数据库连接

# 2. 在数据库中建表（手动或通过 ORM）
# 3. 保存第一个版本
php tools/migrate.php save -m "init users table"

# 4. 修改表结构后保存新版本
php tools/migrate.php save -m "add email column"

# 5. 查看历史
php tools/migrate.php list
php tools/migrate.php check
```

### 迭代开发

```bash
# 1. 显示当前状态
php tools/migrate.php check

# 2. 查看待应用的差异 DDL
php tools/migrate.php diff

# 3. 修改数据库后保存
php tools/migrate.php save -m "add posts table"

# 4. 提交 migrate/ 目录
git add migrate/ && git commit -m "db: add posts table"
```

### 部署

```bash
php tools/migrate.php check
php tools/migrate.php apply
```

### 回滚未提交变更

```bash
# 检测到未提交变更后
php tools/migrate.php check    # 显示差异
php tools/migrate.php clean    # 回滚到本地 head 状态
```

### 从头重建

```bash
php tools/migrate.php reset    # 从 timeline 中最近的快照重建
```

### Squash

当 timeline 过长时，将历史合并为一个基线快照：

```bash
php tools/migrate.php squash -m "milestone 1"
# 创建包含当前全表结构的快照，替换整个 timeline
```

## 规则与约定

1. **禁止修改已提交的迁移文件** — 新的变更请创建新版本
2. **事务保护** — `apply` 和 `reset` 在事务中执行，失败整体回滚
3. **`.migrate` 不提交** — 凭据和 head 状态各环境独立
4. **`migrate/` 提交** — manifest、.sql、.json 文件需提交版本库
5. **SQLite 支持** — DSN 可使用 `sqlite:path/to/db` 或 `sqlite::memory:`，但 `information_schema` 查询仅限 MySQL
