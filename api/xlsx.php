<?php
/**
 * 极简 xlsx 生成器（只用 ZipArchive，不引第三方库）
 *
 * 生成单工作表 Office Open XML 工作簿，带表头底色、全表边框、列宽、
 * 冻结首行、自动筛选、自动换行。Excel / WPS / LibreOffice 都能直接打开，
 * 不会出现"文件格式与扩展名不符"的提示（HTML 伪 xls 会有）。
 *
 * 用法：
 *   require_once __DIR__ . '/xlsx.php';
 *   $binary = xlsx_build(
 *       array(
 *           array('title' => '文件名', 'width' => 28),
 *           array('title' => '下发时间', 'width' => 14, 'align' => 'center'),
 *       ),
 *       array(
 *           array('关于XX的通知', '2026-10-27'),
 *       ),
 *       '文件呈办情况'
 *   );
 */

/** 列号转字母：0 -> A, 25 -> Z, 26 -> AA */
function xlsx_column_letter($index)
{
    $letters = '';
    $index = (int) $index + 1;
    while ($index > 0) {
        $mod = ($index - 1) % 26;
        $letters = chr(65 + $mod) . $letters;
        $index = (int) (($index - $mod) / 26);
    }
    return $letters;
}

/** XML 文本转义（保留换行，交给 xml:space="preserve"） */
function xlsx_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * 构建 xlsx 二进制内容。
 *
 * @param array  $columns 列定义：title(必填) / width(字符宽度) / align(text|center)
 * @param array  $rows    二维数组，行内元素顺序对应列
 * @param string $sheetName 工作表名（Excel 限制 31 字符、不能含 : \ / ? * [ ]）
 * @return string|false  成功返回二进制，失败返回 false
 */
function xlsx_build(array $columns, array $rows, $sheetName = 'Sheet1')
{
    if (!class_exists('ZipArchive')) {
        return false;
    }
    if (empty($columns)) {
        return false;
    }

    $colCount = count($columns);
    $lastCol = xlsx_column_letter($colCount - 1);

    // ---- 列宽 ----
    $colsXml = '<cols>';
    foreach ($columns as $i => $col) {
        $width = isset($col['width']) ? (float) $col['width'] : 20;
        $n = $i + 1;
        $colsXml .= '<col min="' . $n . '" max="' . $n . '" width="' . $width . '" customWidth="1"/>';
    }
    $colsXml .= '</cols>';

    // ---- 表头（样式 1：强调色底 + 白字加粗） ----
    $rowsXml = '<row r="1" ht="26" customHeight="1">';
    foreach ($columns as $i => $col) {
        $ref = xlsx_column_letter($i) . '1';
        $title = isset($col['title']) ? $col['title'] : '';
        $rowsXml .= '<c r="' . $ref . '" s="1" t="inlineStr"><is><t>' . xlsx_escape($title) . '</t></is></c>';
    }
    $rowsXml .= '</row>';

    // ---- 数据行 ----
    $rowNum = 1;
    foreach ($rows as $row) {
        $rowNum++;
        $rowsXml .= '<row r="' . $rowNum . '" ht="22">';
        for ($i = 0; $i < $colCount; $i++) {
            $ref = xlsx_column_letter($i) . $rowNum;
            $value = isset($row[$i]) ? (string) $row[$i] : '';

            // Excel 单元格上限 32767 字符，超了直接截断，避免文件打不开
            if (mb_strlen($value, 'UTF-8') > 32767) {
                $value = mb_substr($value, 0, 32760, 'UTF-8') . '…';
            }

            $align = isset($columns[$i]['align']) ? $columns[$i]['align'] : 'text';
            $style = ($align === 'center') ? '3' : '2';

            $rowsXml .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr">'
                . '<is><t xml:space="preserve">' . xlsx_escape($value) . '</t></is></c>';
        }
        $rowsXml .= '</row>';
    }

    $lastRow = max(1, $rowNum);

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="A1:' . $lastCol . $lastRow . '"/>'
        . '<sheetViews><sheetView tabSelected="1" workbookViewId="0">'
        . '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/>'
        . '<selection pane="bottomLeft" activeCell="A2" sqref="A2"/>'
        . '</sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="18"/>'
        . $colsXml
        . '<sheetData>' . $rowsXml . '</sheetData>'
        . ($lastRow > 1 ? '<autoFilter ref="A1:' . $lastCol . '1"/>' : '')
        . '<pageMargins left="0.4" right="0.4" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
        . '</worksheet>';

    // ---- 样式表 ----
    // cellXfs: 0 默认 / 1 表头 / 2 正文(自动换行、顶端对齐) / 3 居中
    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="3">'
        . '<font><sz val="11"/><name val="微软雅黑"/><family val="2"/></font>'
        . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="微软雅黑"/><family val="2"/></font>'
        . '<font><sz val="11"/><color rgb="FF101828"/><name val="微软雅黑"/><family val="2"/></font>'
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FF3F51B5"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border>'
        . '<left style="thin"><color rgb="FFE4E7EC"/></left>'
        . '<right style="thin"><color rgb="FFE4E7EC"/></right>'
        . '<top style="thin"><color rgb="FFE4E7EC"/></top>'
        . '<bottom style="thin"><color rgb="FFE4E7EC"/></bottom>'
        . '<diagonal/>'
        . '</border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="4">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0"'
        . ' applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
        . '<alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0"'
        . ' applyFont="1" applyBorder="1" applyAlignment="1">'
        . '<alignment horizontal="left" vertical="top" wrapText="1"/></xf>'
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0"'
        . ' applyFont="1" applyBorder="1" applyAlignment="1">'
        . '<alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="常规" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    // ---- 其余 OOXML 部件 ----
    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    // 工作表名去掉 Excel 不允许的字符，并截到 31 字符
    $safeName = preg_replace('/[:\\\\\/\?\*\[\]]/u', '', (string) $sheetName);
    if ($safeName === '' || $safeName === null) {
        $safeName = 'Sheet1';
    }
    $safeName = mb_substr($safeName, 0, 31, 'UTF-8');

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="' . xlsx_escape($safeName) . '" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n"
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    // ---- 打包 ----
    // 六个部件就是 xlsx 的全部内容，打成 ZIP 即成品
    $entries = array(
        '[Content_Types].xml' => $contentTypes,
        '_rels/.rels' => $rootRels,
        'xl/workbook.xml' => $workbook,
        'xl/_rels/workbook.xml.rels' => $workbookRels,
        'xl/styles.xml' => $stylesXml,
        'xl/worksheets/sheet1.xml' => $sheetXml,
    );

    // 有 ZipArchive 就用它；没有（服务器没装 zip 扩展）就走纯 PHP 打包。
    // XLSX_FORCE_PURE_ZIP 常量用于强制走纯 PHP 路径，方便验证。
    $forcePure = (defined('XLSX_FORCE_PURE_ZIP') && XLSX_FORCE_PURE_ZIP);

    if (class_exists('ZipArchive') && !$forcePure) {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            // ZipArchive 打不开就退回纯 PHP，不再往下抛错
            return xlsx_zip_bytes($entries);
        }

        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $binary = file_get_contents($tmp);
        @unlink($tmp);

        return ($binary === false) ? xlsx_zip_bytes($entries) : $binary;
    }

    return xlsx_zip_bytes($entries);
}

/**
 * 纯 PHP 打包 ZIP（不依赖 zip 扩展）。
 *
 * 只实现 xlsx 需要的子集：每个条目一个本地文件头 + 数据，
 * 末尾追加中央目录和 EOCD。有 zlib 时用 deflate（method 8），
 * 没有就全部 STORED（method 0），两种 Excel 都能读。
 *
 * 不处理：加密、ZIP64（>4GB）、分卷、目录条目。
 *
 * @param array $entries  ['内部路径' => '文件内容']
 * @return string|false
 */
function xlsx_zip_bytes(array $entries)
{
    if (empty($entries)) {
        return false;
    }

    // 有 zlib 就 deflate；没有就全 STORED（不压缩，文件大些但结构合法）
    // XLSX_FORCE_STORE 常量用于强制不压缩，方便验证
    $useDeflate = function_exists('gzdeflate')
        && !(defined('XLSX_FORCE_STORE') && XLSX_FORCE_STORE);

    // DOS 时间格式：秒要除以 2，年份从 1980 起算
    $now = getdate();
    $dosTime = (($now['hours'] & 0x1F) << 11) | (($now['minutes'] & 0x3F) << 5) | (($now['seconds'] >> 1) & 0x1F);
    $dosDate = ((($now['year'] - 1980) & 0x7F) << 9) | (($now['mon'] & 0x0F) << 5) | ($now['mday'] & 0x1F);

    $local = '';
    $central = '';
    $offset = 0;
    $count = 0;

    foreach ($entries as $name => $content) {
        $name = str_replace('\\', '/', (string) $name);
        $content = (string) $content;
        $crc = crc32($content);
        $rawSize = strlen($content);

        $method = 0;
        $payload = $content;

        if ($useDeflate && $rawSize > 64) {
            $deflated = gzdeflate($content, 6);
            // 压不小就存原样，别做负优化
            if ($deflated !== false && strlen($deflated) < $rawSize) {
                $payload = $deflated;
                $method = 8;
            }
        }

        $compSize = strlen($payload);
        $nameLen = strlen($name);

        // 本地文件头：固定 30 字节 + 文件名
        $local .= pack('V', 0x04034b50)
            . pack('v', 20)          // version needed
            . pack('v', 0x0800)      // flags：文件名 UTF-8
            . pack('v', $method)
            . pack('v', $dosTime)
            . pack('v', $dosDate)
            . pack('V', $crc)
            . pack('V', $compSize)
            . pack('V', $rawSize)
            . pack('v', $nameLen)
            . pack('v', 0)           // extra field 长度
            . $name
            . $payload;

        // 中央目录项：固定 46 字节 + 文件名
        $central .= pack('V', 0x02014b50)
            . pack('v', 20)          // version made by
            . pack('v', 20)          // version needed
            . pack('v', 0x0800)
            . pack('v', $method)
            . pack('v', $dosTime)
            . pack('v', $dosDate)
            . pack('V', $crc)
            . pack('V', $compSize)
            . pack('V', $rawSize)
            . pack('v', $nameLen)
            . pack('v', 0)           // extra
            . pack('v', 0)           // comment
            . pack('v', 0)           // 起始磁盘号
            . pack('v', 0)           // 内部属性
            . pack('V', 0)           // 外部属性
            . pack('V', $offset)     // 本地头偏移
            . $name;

        $offset += 30 + $nameLen + $compSize;
        $count++;
    }

    $cdOffset = strlen($local);

    // EOCD
    $eocd = pack('V', 0x06054b50)
        . pack('v', 0)               // 当前磁盘号
        . pack('v', 0)               // 中央目录起始磁盘号
        . pack('v', $count)          // 本盘条目数
        . pack('v', $count)          // 总条目数
        . pack('V', strlen($central))
        . pack('V', $cdOffset)
        . pack('v', 0);              // 注释长度

    return $local . $central . $eocd;
}
