<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple XLSX writer without external dependencies (ZipArchive only).
 * Rows are written incrementally to a temporary file so exports of large
 * shops do not use much memory. Text cells are stored as inlineStr, so Excel
 * never evaluates a value (for example one starting with = or +) as a formula.
 */
// Rows are streamed into temporary files; direct file functions are intentional (WP_Filesystem cannot append/stream).
// phpcs:disable WordPress.WP.AlternativeFunctions
class EPM_Xlsx_Writer {
	private $rows_file;
	private $row_count = 0;
	private $col_count = 0;
	private $widths    = array();

	public function __construct( $rows_file ) {
		$this->rows_file = $rows_file;
	}

	public static function col_letter( $index ) {
		$letter = '';
		++$index;
		while ( $index > 0 ) {
			$mod    = ( $index - 1 ) % 26;
			$letter = chr( 65 + $mod ) . $letter;
			$index  = (int) ( ( $index - $mod ) / 26 );
		}
		return $letter;
	}

	private static function esc( $value ) {
		$value = (string) $value;
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value );
		if ( $value === null ) {
			$value = '';
		}
		return htmlspecialchars( $value, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * @param array $cells  Each item: a plain value or ['v' => value, 'n' => true for a numeric cell]
	 * @param bool  $header Header row (bold style)
	 */
	public function add_row( array $cells, $header = false ) {
		++$this->row_count;
		$r   = $this->row_count;
		$xml = '<row r="' . $r . '">';
		$i   = 0;
		foreach ( $cells as $cell ) {
			$numeric = false;
			if ( is_array( $cell ) ) {
				$numeric = ! empty( $cell['n'] );
				$cell    = $cell['v'];
			}
			$ref   = self::col_letter( $i ) . $r;
			$style = $header ? ' s="1"' : '';
			if ( '' === $cell || null === $cell ) {
				// Empty cells are not written.
				++$i;
				continue;
			}
			if ( $numeric && is_numeric( $cell ) ) {
				$xml .= '<c r="' . $ref . '"' . $style . '><v>' . ( 0 + $cell ) . '</v></c>';
			} else {
				$len = function_exists( 'mb_strlen' ) ? mb_strlen( (string) $cell ) : strlen( (string) $cell );
				if ( ! isset( $this->widths[ $i ] ) || $this->widths[ $i ] < $len ) {
					$this->widths[ $i ] = min( 60, $len );
				}
				$xml .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . self::esc( $cell ) . '</t></is></c>';
			}
			++$i;
		}
		$xml .= '</row>';
		if ( $i > $this->col_count ) {
			$this->col_count = $i;
		}
		file_put_contents( $this->rows_file, $xml, FILE_APPEND | LOCK_EX );
	}

	public function get_state() {
		return array(
			'rows'   => $this->row_count,
			'cols'   => $this->col_count,
			'widths' => $this->widths,
		);
	}

	public function set_state( $state ) {
		$this->row_count = (int) ( $state['rows'] ?? 0 );
		$this->col_count = (int) ( $state['cols'] ?? 0 );
		$this->widths    = (array) ( $state['widths'] ?? array() );
	}

	/** Build the final xlsx file. */
	public function finish( $xlsx_path, $sheet_title = 'Products' ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'epm_no_zip', __( 'The ZipArchive extension is not enabled on the server.', 'expert-price-manager' ) );
		}
		$cols = '';
		if ( $this->col_count ) {
			$cols = '<cols>';
			for ( $i = 0; $i < $this->col_count; $i++ ) {
				$w     = isset( $this->widths[ $i ] ) ? max( 10, min( 60, $this->widths[ $i ] + 3 ) ) : 12;
				$cols .= '<col min="' . ( $i + 1 ) . '" max="' . ( $i + 1 ) . '" width="' . $w . '" customWidth="1"/>';
			}
			$cols .= '</cols>';
		}
		$last = self::col_letter( max( 0, $this->col_count - 1 ) ) . max( 1, $this->row_count );
		$head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetViews><sheetView workbookViewId="0" rightToLeft="1"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
			. '<sheetFormatPr defaultRowHeight="15"/>' . $cols . '<sheetData>';
		$tail = '</sheetData><autoFilter ref="A1:' . $last . '"/></worksheet>';

		$sheet_tmp = $xlsx_path . '.sheet';
		$out       = fopen( $sheet_tmp, 'wb' );
		fwrite( $out, $head );
		$in = fopen( $this->rows_file, 'rb' );
		if ( $in ) {
			stream_copy_to_stream( $in, $out );
			fclose( $in );
		}
		fwrite( $out, $tail );
		fclose( $out );

		$zip = new ZipArchive();
		if ( $zip->open( $xlsx_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== true ) {
			wp_delete_file( $sheet_tmp );
			return new WP_Error( 'epm_zip_open', __( 'Could not create the xlsx file.', 'expert-price-manager' ) );
		}
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' );
		$zip->addFromString( 'xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . self::esc( $sheet_title ) . '" sheetId="1" r:id="rId1"/></sheets></workbook>' );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' );
		$zip->addFromString( 'xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Tahoma"/></font><font><b/><sz val="11"/><name val="Tahoma"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8EEF7"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>' );
		$zip->addFile( $sheet_tmp, 'xl/worksheets/sheet1.xml' );
		$ok = $zip->close();
		wp_delete_file( $sheet_tmp );
		return $ok ? true : new WP_Error( 'epm_zip_close', __( 'Writing the xlsx file failed.', 'expert-price-manager' ) );
	}
}
