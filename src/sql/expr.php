<?php
declare(strict_types=1);
namespace ff\helpers\sql;
/**
 * @method operate add(mixed $any)              加法运算符
 * @method operate sub(mixed $any)              减法运算符
 * @method operate mul(mixed $any)              乘法运算符
 * @method operate div(mixed $any)              除法运算符
 * @method operate mod(mixed $any)              取模运算符
 * @method operate eq(mixed $any)               等于运算符
 * @method operate ne(mixed $any)               不等于运算符
 * @method operate lt(mixed $any)               小于运算符
 * @method operate le(mixed $any)               小于等于运算符
 * @method operate gt(mixed $any)               大于运算符
 * @method operate ge(mixed $any)               大于等于运算符
 * @method operate nullsafe_eq(mixed $any)      安全空值比较运算符
 * @method operate like(string $str)             模糊匹配运算符（LIKE）
 * @method operate rlike(string $str)            正则表达式匹配运算符（REGEXP）
 * @method operate regexp(string $str)           同上
 * @method operate operate($N2, $operator = '=')
 * @method operate equal($N2) =
 * @method operate not_between($min, $max) expr NOT BETWEEN min AND max
 * @method operate between($min, $max) expr BETWEEN min AND max 假如expr大于或等于 min 且expr 小于或等于max, 则BETWEEN 的返回值为1, 或是0
 * @method operate not_in($expr, ...$values) expr NOT IN (value, ...)
 * @method operate in($expr, ...$values) expr IN (value, ...) 若expr 为IN列表中的任意一个值，则其返回值为 1, 否则返回值为0
 * @method operate not() NOT ! 逻辑 NOT。当操作数为0 时，所得值为 1 ；当操作数为非零值时，所得值为  0 ，而当操作数为NOT NULL时，所得的返回值为 NULL
 * @method operate and ($expr2) AND && 逻辑AND。当所有操作数均为非零值、并且不为NULL时，计算所得结果为  1 ，当一个或多个操作数为0 时，所得结果为 0 ，其余情况返回值为 NULL
 * @method operate or ($expr2) OR || 逻辑 OR。当两个操作数均为非 NULL值时，如有任意一个操作数为非零值，则结果为1，否则结果为0。当有一个操作数为NULL时，如另一个操作数为非零值，则结果为1，否则结果为 NULL 。假如两个操作数均为  NULL，则所得结果为 NULL
 * @method operate xor ($expr2) XOR 逻辑XOR。当任意一个操作数为 NULL时，返回值为NULL。对于非   NULL 的操作数，假如一个奇数操作数为非零值，则计算所得结果为  1 ，否则为  0
 * @method operate ABS() - ABS(expr) 返回绝对值
 * @method operate ACOS() - ACOS(expr) 返回反余弦值
 * @method operate ADDDATE(string $interval) - ADDDATE(date, interval) 将时间间隔加到日期上
 * @method operate ADDTIME(string $interval) - ADDTIME(time, interval) 将时间间隔加到时间上
 * @method operate AES_DECRYPT(string $key) - AES_DECRYPT(data, key) 使用AES解密数据
 * @method operate AES_ENCRYPT(string $key) - AES_ENCRYPT(data, key) 使用AES加密数据
 * @method operate ANY_VALUE() - ANY_VALUE(expr) 抑制 ONLY_FULL_GROUP_BY 错误
 * @method operate ASCII() - ASCII(str) 返回字符串最左字符的ASCII码
 * @method operate ASIN() - ASIN(expr) 返回反正弦值
 * @method operate ATAN() - ATAN(expr) 返回反正切值
 * @method operate ATAN2(float $x) - ATAN2(y, x) 返回两参数的反正切值
 * @method operate AVG() - AVG(expr) 返回平均值
 * @method operate BENCHMARK(int $n) - BENCHMARK(n, expr) 重复执行表达式
 * @method operate BIN() - BIN(num) 返回数字的二进制字符串表示
 * @method operate BIN_TO_UUID() - BIN_TO_UUID(bin) 将二进制UUID转换为字符串
 * @method operate BINARY() - BINARY(str) 将字符串转换为二进制字符串
 * @method operate BIT_AND(array $expr_list) - BIT_AND(expr_list) 返回按位与结果
 * @method operate BIT_COUNT() - BIT_COUNT(expr) 返回设置的位数
 * @method operate BIT_LENGTH() - BIT_LENGTH(expr) 返回参数的位数
 * @method operate BIT_OR(array $expr_list) - BIT_OR(expr_list) 返回按位或结果
 * @method operate BIT_XOR(array $expr_list) - BIT_XOR(expr_list) 返回按位异或结果
 * @method operate CAST(string $type) - CAST(expr AS type) 将值转换为指定类型
 * @method operate CEIL() - CEIL(expr) 返回不小于该值的最小整数
 * @method operate CEILING() - CEILING(expr) 返回不小于该值的最小整数
 * @method operate CHAR() - CHAR(num) 返回指定整数对应的字符
 * @method operate CHAR_LENGTH() - CHAR_LENGTH(str) 返回字符串字符数
 * @method operate CHARACTER_LENGTH() - CHARACTER_LENGTH(str) 返回字符串字符数（CHAR_LENGTH的别名）
 * @method operate CHARSET() - CHARSET(str) 返回字符串字符集
 * @method operate COALESCE(mixed ...$expr) - COALESCE(expr1, expr2, ...) 返回第一个非NULL值
 * @method operate COERCIBILITY() - COERCIBILITY(str) 返回字符串的排序强制值
 * @method operate COLLATION() - COLLATION(str) 返回字符串的排序规则
 * @method operate COMPRESS() - COMPRESS(str) 返回压缩后的二进制字符串
 * @method operate CONCAT(string ...$str) - CONCAT(str1, str2, ...) 返回拼接字符串
 * @method operate CONCAT_WS(string $separator, string ...$str) - CONCAT_WS(separator, str1, str2, ...) 返回拼接字符串，中间用分隔符
 * @method operate CONNECTION_ID() - CONNECTION_ID() 返回当前连接ID
 * @method operate CONV(int $from_base, int $to_base) - CONV(num, from_base, to_base) 在不同进制间转换数字
 * @method operate CONVERT(string $type) - CONVERT(expr, type) 将值转换为指定类型
 * @method operate CONVERT_TZ(string $from_tz, string $to_tz) - CONVERT_TZ(datetime, from_tz, to_tz) 转换时区
 * @method operate COS() - COS(expr) 返回余弦值
 * @method operate COT() - COT(expr) 返回余切值
 * @method operate COUNT() - COUNT(expr) 返回符合条件的行数
 * -method operate COUNT(DISTINCT ) - COUNT(DISTINCT expr) 返回不同值的行数
 * @method operate CRC32() - CRC32(expr) 计算循环冗余校验值
 * @method operate DATE() - DATE(date) 提取日期部分
 * @method operate DATE_ADD(string $interval) - DATE_ADD(date, interval) 将时间间隔加到日期上
 * @method operate DATE_FORMAT(string $format) - DATE_FORMAT(date, format) 格式化日期
 * @method operate DATE_SUB(string $interval) - DATE_SUB(date, interval) 从日期减去时间间隔
 * @method operate DATEDIFF(string $date2) - DATEDIFF(date1, date2) 计算两个日期差值
 * @method operate DAY() - DAY(date) 返回日期的天数（0-31）
 * @method operate DAYNAME() - DAYNAME(date) 返回星期名称
 * @method operate DAYOFMONTH() - DAYOFMONTH(date) 返回日期的月内天数（0-31）
 * @method operate DAYOFWEEK() - DAYOFWEEK(date) 返回星期索引（0-6）
 * @method operate DAYOFYEAR() - DAYOFYEAR(date) 返回日期的年中天数（1-366）
 * @method operate DEFAULT() - DEFAULT(col) 返回表列的默认值
 * @method operate DEGREES() - DEGREES(radians) 将弧度转换为角度
 * @method operate DENSE_RANK() - DENSE_RANK() 返回分区内的排名（无间隙）
 * -method operate DIV(int $expr2) - DIV(expr1 DIV expr2) 整数除法
 * @method operate ELT(string ...$str) - ELT(index, str1, str2, ...) 返回指定索引的字符串
 * @method operate EXP() - EXP(expr) 返回指数值（e^expr）
 * @method operate EXPORT_SET(int $bits, string $on_str, string $off_str, int $default_on, int $default_off) - EXPORT_SET(bits, on_str, off_str, default_on, default_off) 返回设置位的字符串
 * @method operate EXTRACT(string $date_part) - EXTRACT(date_part FROM date) 提取日期部分
 * @method operate FIELD(string ...$str_list) - FIELD(str, str1, str2, ...) 返回字符串在列表中的位置
 * @method operate FIND_IN_SET(string $str_list) - FIND_IN_SET(str, str_list) 返回字符串在列表中的位置
 * @method operate FIRST_VALUE() - FIRST_VALUE(expr) 返回窗口帧中第一行的值
 * @method operate FLOOR() - FLOOR(expr) 返回不大于该值的最大整数
 * @method operate FORMAT(int $dec_places) - FORMAT(num, dec_places) 格式化数字为指定小数位数
 * @method operate FORMAT_BYTES() - FORMAT_BYTES(bytes) 将字节数转换为带单位的值
 * @method operate FORMAT_PICO_TIME() - FORMAT_PICO_TIME(pico_seconds) 将皮秒转换为带单位的值
 * @method operate FROM_BASE64() - FROM_BASE64(str) 解码Base64字符串
 * @method operate FROM_DAYS() - FROM_DAYS(days) 将天数转换为日期
 * @method operate FROM_UNIXTIME() - FROM_UNIXTIME(timestamp) 将Unix时间戳格式化为日期
 * @method operate GET_LOCK(string $timeout) - GET_LOCK(lock_name, timeout) 获取命名锁
 * @method operate GREATEST(mixed ...$expr) - GREATEST(expr1, expr2, ...) 返回最大值
 * @method operate GROUP_CONCAT() - GROUP_CONCAT(expr) 返回拼接字符串
 * @method operate HEX(int|string $num) - HEX(num) 返回十进制或字符串的十六进制表示
 * @method operate HOUR() - HOUR(time) 提取小时
 * @method operate IF(bool $condition, mixed $false_value) - IF(condition, true_value, false_value) 条件判断
 * @method operate IFNULL(mixed $expr2) - IFNULL(expr1, expr2) NULL判断
 * @method operate INET_ATON() - INET_ATON(ip) 返回IP地址的数值
 * @method operate INET_NTOA() - INET_NTOA(num) 返回IP地址
 * @method operate INSERT(int $pos, int $len) - INSERT(str, pos, len) 在指定位置插入子串
 * @method operate INSTR(string $substring) - INSTR(str, substring) 返回子串首次出现位置
 * @method operate INTERVAL(string $unit) - INTERVAL(expr, unit) 返回小于第一个参数的索引
 * @method operate IS_FREE_LOCK() - IS FREE_LOCK(lock_name) 判断锁是否空闲
 * @method operate IS_USED_LOCK() - IS_USED_LOCK(lock_name) 判断锁是否被使用
 * @method operate IS_UUID() - IS_UUID(expr) 判断是否为有效UUID
 * @method operate ISNULL() - ISNULL(expr) 判断是否为NULL
 * @method operate JSON_ARRAY_APPEND(string $path, mixed $value) - JSON_ARRAY_APPEND(json_doc, path, value) 在JSON文档中追加数据
 * @method operate JSON_ARRAY_INSERT(string $path, mixed $value) - JSON_ARRAY_INSERT(json_doc, path, value) 在JSON数组中插入数据
 * @method operate JSON_CONTAINS(string $path) - JSON_CONTAINS(json_doc, path) 判断JSON文档是否包含指定路径
 * @method operate JSON_CONTAINS_PATH(string $path) - JSON_CONTAINS_PATH(json_doc, path) 判断JSON文档是否包含指定路径
 * @method operate JSON_DEPTH() - JSON_DEPTH(json_doc) 返回JSON文档最大深度
 * @method operate JSON_EXTRACT(string $path) - JSON_EXTRACT(json_doc, path) 从JSON文档提取数据
 * @method operate JSON_INSERT(string $path, mixed $value) - JSON_INSERT(json_doc, path, value) 在JSON文档中插入数据
 * @method operate JSON_KEYS() - JSON_KEYS(json_doc) 返回JSON文档的键列表
 * @method operate JSON_LENGTH() - JSON_LENGTH(json_doc) 返回JSON文档元素数
 * @method operate JSON_MERGE(string $json_doc2) - JSON_MERGE(json_doc1, json_doc2) 合并JSON文档（保留重复键）
 * @method operate JSON_MERGE_PATCH(string $json_doc2) - JSON_MERGE_PATCH(json_doc1, json_doc2) 合并JSON文档（覆盖重复键）
 * @method operate JSON_MERGE_PRESERVE(string $json_doc2) - JSON_MERGE_PRESERVE(json_doc1, json_doc2) 合并JSON文档（保留重复键）
 * @method operate JSON_OVERLAPS(string $json_doc2) - JSON_OVERLAPS(json_doc1, json_doc2) 判断两个JSON文档是否有共同键值或数组元素
 * @method operate JSON_PRETTY() - JSON_PRETTY(json_doc) 以可读格式打印JSON文档
 * @method operate JSON_QUOTE() - JSON_QUOTE(json_doc) 对JSON文档进行转义
 * @method operate JSON_REMOVE(string $path) - JSON_REMOVE(json_doc, path) 从JSON文档中删除数据
 * @method operate JSON_REPLACE(string $path, mixed $value) - JSON_REPLACE(json_doc, path, value) 替换JSON文档中的值
 * @method operate JSON_SCHEMA_VALID(string $schema) - JSON_SCHEMA_VALID(json_doc, schema) 验证JSON文档是否符合JSON Schema
 * @method operate JSON_SCHEMA_VALIDATION_REPORT(string $schema) - JSON_SCHEMA_VALIDATION_REPORT(json_doc, schema) 返回JSON验证报告
 * @method operate JSON_SEARCH(string $path) - JSON_SEARCH(json_doc, path) 返回JSON文档中值的路径
 * @method operate JSON_SET(string $path, mixed $value) - JSON_SET(json_doc, path, value) 在JSON文档中插入数据
 * @method operate JSON_STORAGE_FREE() - JSON_STORAGE_FREE(json_doc) 返回JSON列值部分更新后释放的空间
 * @method operate JSON_STORAGE_SIZE() - JSON_STORAGE_SIZE(json_doc) 返回JSON文档存储空间大小
 * @method operate JSON_TABLE(string $path) - JSON_TABLE(json_expr, path) 将JSON表达式作为关系表返回
 * @method operate JSON_TYPE() - JSON_TYPE(json_doc) 返回JSON值类型
 * @method operate JSON_UNQUOTE() - JSON_UNQUOTE(json_doc) 去引号JSON值
 * @method operate JSON_VALID() - JSON_VALID(json_doc) 判断JSON值是否有效
 * @method operate JSON_VALUE(string $path) - JSON_VALUE(json_doc, path) 从JSON文档提取值
 * @method operate LAG() - LAG(expr) 返回当前行前的值（窗口帧）
 * @method operate LAST_DAY() - LAST_DAY(date) 返回指定月份的最后一天
 * @method operate LAST_VALUE() - LAST_VALUE(expr) 返回窗口帧中最后一行的值
 * @method operate LCASE() - LCASE(str) 返回小写字符串（LOWER的别名）
 * @method operate LEAD() - LEAD(expr) 返回当前行后的值（窗口帧）
 * @method operate LEAST(mixed ...$expr) - LEAST(expr1, expr2, ...) 返回最小值
 * @method operate LEFT(int $len) - LEFT(str, len) 返回字符串左部指定字符数
 * @method operate LENGTH() - LENGTH(str) 返回字符串字节数
 * @method operate LN() - LN(expr) 返回自然对数
 * @method operate LOAD_FILE() - LOAD_FILE(file_path) 加载指定文件
 * @method operate LOCATE(string $str) - LOCATE(substr, str) 返回子串首次出现位置
 * @method operate LOG() - LOG(expr) 返回自然对数
 * @method operate LOG10() - LOG10(expr) 返回以10为底的对数
 * @method operate LOG2() - LOG2(expr) 返回以2为底的对数
 * @method operate LOWER() - LOWER(str) 返回小写字符串
 * @method operate LPAD(int $len, string $pad_str) - LPAD(str, len, pad_str) 字符串左补指定字符
 * @method operate LTRIM() - LTRIM(str) 删除开头空格
 * @method operate MAKE_SET(string ...$str) - MAKE_SET(bits, str1, str2, ...) 返回设置位的字符串集合
 * @method operate MAKEDATE(int $day_of_year) - MAKEDATE(year, day_of_year) 由年和年中天数创建日期
 * @method operate MAKETIME(int $minute, int $second) - MAKETIME(hour, minute, second) 由小时、分钟、秒创建时间
 * @method operate MASTER_POS_WAIT() - MASTER_POS_WAIT(pos) 等待从属节点应用到指定位置
 * @method operate MATCH(string $query) _MATCH(str) AGAINST (query) 全文搜索
 * @method operate MAX() - MAX(expr) 返回最大值
 * @method operate MD5() - MD5(str) 计算MD5校验和
 * @method operate MEMBER_OF(string $json_array) - MEMBER OF(json_doc, json_array) 判断JSON数组是否包含指定元素
 * @method operate MICROSECOND() - MICROSECOND(time) 返回微秒
 * @method operate MID(int $start, int $len) - MID(str, start, len) 返回从指定位置开始的子串
 * @method operate MIN() - MIN(expr) 返回最小值
 * @method operate MINUTE() - MINUTE(time) 返回分钟
 * -method operate MOD(int $expr2) - MOD(expr1, expr2) 返回余数
 * @method operate MONTH() - MONTH(date) 返回月份
 * @method operate MONTHNAME() - MONTHNAME(date) 返回月份名称
 * @method operate NAME_CONST(mixed $value) - NAME_CONST(col_name, value) 为列指定名称
 * @method operate NOT_EXISTS() - NOT EXISTS(subquery) 判断子查询是否无行
 * @method operate NTH_VALUE() - NTH_VALUE(expr) 返回窗口帧中第N行的值
 * @method operate NTILE(int $n) - NTILE(n) 返回分区内的桶编号
 * @method operate NULLIF(mixed $expr2) - NULLIF(expr1, expr2) 如果两个表达式相等则返回NULL
 * @method operate OCT() - OCT(num) 返回数字的八进制字符串表示
 * @method operate OCTET_LENGTH() - OCTET_LENGTH(str) 返回字符串字节数（LENGTH的别名）
 * @method operate ORD() - ORD(str) 返回字符串最左字符的ASCII码
 * @method operate PERIOD_ADD(int $months) - PERIOD_ADD(period, months) 将月份加到年月上
 * @method operate PERIOD_DIFF(int $period2) - PERIOD_DIFF(period1, period2) 返回两个时期之间的月份数
 * @method operate POSITION(string $str) - POSITION(substr IN str) 返回子串首次出现位置（LOCATE的别名）
 * @method operate POW(float $expr2) - POW(expr1, expr2) 返回指数值
 * @method operate POWER(float $expr2) - POWER(expr1, expr2) 返回指数值
 * @method operate RANDOM_BYTES() - RANDOM_BYTES(n) 返回随机字节数组
 * @method operate RANK() - RANK() 返回分区内的排名（有间隙）
 * @method operate REGEXP_INSTR(string $pattern) - REGEXP_INSTR(str, pattern) 返回匹配正则表达式的起始位置
 * @method operate REGEXP_LIKE(string $pattern) - REGEXP_LIKE(str, pattern) 是否匹配正则表达式
 * @method operate REGEXP_REPLACE(string $pattern, string $replacement) - REGEXP_REPLACE(str, pattern, replacement) 替换正则表达式匹配部分
 * @method operate REGEXP_SUBSTR(string $pattern) - REGEXP_SUBSTR(str, pattern) 返回正则表达式匹配子串
 * @method operate RELEASE_LOCK() - RELEASE_LOCK(lock_name) 释放命名锁
 * @method operate REPEAT(int $n) - REPEAT(str, n) 返回字符串重复n次
 * @method operate REPLACE(string $old, string $new) - REPLACE(str, old, new) 替换字符串中指定部分
 * @method operate REVERSE() - REVERSE(str) 返回字符串反转
 * @method operate RIGHT(int $len) - RIGHT(str, len) 返回字符串右部指定字符数
 * @method operate ROUND(int $dec_places) - ROUND(expr, dec_places) 四舍五入
 * @method operate RPAD(int $len, string $pad_str) - RPAD(str, len, pad_str) 字符串右补指定字符
 * @method operate RTRIM() - RTRIM(str) 删除末尾空格
 * @method operate SEC_TO_TIME() - SEC_TO_TIME(seconds) 将秒转换为时间格式
 * @method operate SECOND() - SECOND(time) 返回秒数
 * @method operate SHA1() - SHA1(str) 计算SHA-1校验和
 * @method operate SHA2(int $bits) - SHA2(str, bits) 计算SHA-2校验和
 * @method operate SIGN() - SIGN(expr) 返回表达式的符号
 * @method operate SIN() - SIN(expr) 返回正弦值
 * @method operate SLEEP() - SLEEP(seconds) 睡眠指定秒数
 * @method operate SOUNDEX() - SOUNDEX(str) 返回声音相似字符串
 * @method operate SPACE() - SPACE(n) 返回n个空格字符串
 * @method operate SQRT() - SQRT(expr) 返回平方根
 * @method operate STD() - STD(expr) 返回总体标准差
 * @method operate STDDEV() - STDDEV(expr) 返回总体标准差
 * @method operate STDDEV_POP() - STDDEV_POP(expr) 返回总体标准差
 * @method operate STDDEV_SAMP() - STDDEV_SAMP(expr) 返回样本标准差
 * @method operate STR_TO_DATE(string $format) - STR_TO_DATE(str, format) 将字符串转换为日期
 * @method operate STRCMP(string $str2) - STRCMP(str1, str2) 比较两个字符串
 * @method operate SUBDATE(string $interval) - SUBDATE(date, interval) 从日期减去时间间隔（DATE_SUB的别名）
 * @method operate SUBSTR(int $start, int $len) - SUBSTR(str, start, len) 返回子串
 * @method operate SUBSTRING(int $start, int $len) - SUBSTRING(str, start, len) 返回子串
 * @method operate SUBSTRING_INDEX(string $delimiter, int $count) - SUBSTRING_INDEX(str, delimiter, count) 返回子字符串（根据分隔符次数）
 * @method operate SUBTIME(string $time2) - SUBTIME(time1, time2) 时间相减
 * @method operate SUM() - SUM(expr) 返回总和
 * @method operate TAN() - TAN(expr) 返回正切值
 * @method operate TIME() - TIME(datetime) 提取时间部分
 * @method operate TIME_FORMAT(string $format) - TIME_FORMAT(time, format) 格式化时间
 * @method operate TIME_TO_SEC() - TIME_TO_SEC(time) 将时间转换为秒
 * @method operate TIMEDIFF(string $time2) - TIMEDIFF(time1, time2) 计算时间差
 * @method operate TIMESTAMP(mixed $expr) - TIMESTAMP(expr) 返回日期或时间表达式；两个参数则求和
 * @method operate TIMESTAMPADD(string $unit, int $interval) - TIMESTAMPADD(unit, interval, datetime) 将时间间隔加到日期表达式上
 * @method operate TIMESTAMPDIFF(string $unit, string $datetime2) - TIMESTAMPDIFF(unit, datetime1, datetime2) 计算两个日期表达式之间的差值
 * @method operate TO_BASE64() - TO_BASE64(str) 将字符串转换为Base64字符串
 * @method operate TO_DAYS() - TO_DAYS(date) 将日期转换为天数
 * @method operate TO_SECONDS() - TO_SECONDS(date) 将日期或时间转换为自公元0年的秒数
 * @method operate TRIM() - TRIM(str) 删除首尾空格
 * @method operate TRUNCATE(int $dec_places) - TRUNCATE(expr, dec_places) 截断到指定小数位数
 * @method operate UCASE() - UCASE(str) 返回大写字符串（UPPER的别名）
 * @method operate UNCOMPRESS() - UNCOMPRESS(str) 解压缩字符串
 * @method operate UNCOMPRESSED_LENGTH() - UNCOMPRESSED_LENGTH(str) 返回压缩前字符串长度
 * @method operate UNHEX(string $hex_str) - UNHEX(hex_str) 返回十六进制字符串对应的值
 * @method operate UPPER() - UPPER(str) 返回大写字符串
 * @method operate UUID_TO_BIN() - UUID_TO_BIN(uuid_str) 将字符串UUID转换为二进制
 * @method operate VALIDATE_PASSWORD_STRENGTH() - VALIDATE_PASSWORD_STRENGTH(password) 判断密码强度
 * @method operate VALUES(mixed ...$expr) - VALUES(expr1, expr2, ...) 定义INSERT语句中的值
 * @method operate VAR_POP() - VAR_POP(expr) 返回总体方差
 * @method operate VAR_SAMP() - VAR_SAMP(expr) 返回样本方差
 * @method operate VARIANCE() - VARIANCE(expr) 返回总体方差
 * @method operate WAIT_FOR_EXECUTED_GTID_SET() - WAIT_FOR_EXECUTED_GTID_SET(gtid_set) 等待从属节点执行指定GTID
 * @method operate WEEK() - WEEK(date) 返回周数
 * @method operate WEEKDAY() - WEEKDAY(date) 返回星期索引
 * @method operate WEEKOFYEAR() - WEEKOFYEAR(date) 返回年中的周数（1-53）
 * @method operate WEIGHT_STRING() - WEIGHT_STRING(str) 返回字符串权重
 * @method operate YEAR() - YEAR(date) 返回年份
 * @method operate YEARWEEK() - YEARWEEK(date) 返回年和周数
 */
abstract class expr{
	use alias;

	public function __call($name, $arguments): operate{
		return new operate($name, [$this, ...$arguments]);
	}
	abstract public function __toString(): string;
}
