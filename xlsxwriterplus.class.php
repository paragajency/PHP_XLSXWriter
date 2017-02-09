<?php

class XLSWriterPlus extends XLSXWriter
{
    /**
     * @var array
     */
    private $images = [];

    /**
     * @param string $imagePath
     * @param int $imageId
     */
    public function addImage($imagePath, $imageId)
    {
        $this->images[$imageId] = $imagePath;
    }

    public function writeToString()
    {
        $temp_file = $this->tempFilename();
        $this->writeToFile($temp_file);
        $string = file_get_contents($temp_file);

        return $string;
    }

    /**
     * @param string $filename
     */
    public function writeToFile($filename)
    {
        foreach ($this->sheets as $sheet_name => $sheet) {
            $this->finalizeSheet($sheet_name);
        }

        if (file_exists($filename)) {
            if (is_writable($filename)) {
                @unlink($filename);
            } else {
                $this->log("Error in " . __CLASS__ . "::" . __FUNCTION__ . ", file is not writeable.");
                return;
            }
        }

        $zip = new \ZipArchive();
        if (empty($this->sheets)) {
            $this->log("Error in " . __CLASS__ . "::" . __FUNCTION__ . ", no worksheets defined.");
            return;
        }
        if (!$zip->open($filename, \ZipArchive::CREATE)) {
            $this->log("Error in " . __CLASS__ . "::" . __FUNCTION__ . ", unable to create zip.");
            return;
        }

        $zip->addEmptyDir("docProps/");
        $zip->addFromString("docProps/app.xml", $this->buildAppXML());
        $zip->addFromString("docProps/core.xml", $this->buildCoreXML());

        $zip->addEmptyDir("_rels/");
        $zip->addFromString("_rels/.rels", $this->buildRelationshipsXML());

        if (count($this->images) > 0) {
            $zip->addEmptyDir("xl/media/");

            $zip->addEmptyDir("xl/drawings");
            $zip->addEmptyDir("xl/drawings/_rels");

            foreach ($this->images as $imageId => $imagePath) {
                $imageName = explode('/', $imagePath);
                $imageName = end($imageName);

                $zip->addFile($imagePath, 'xl/media/' . $imageName);

                $zip->addFromString("xl/drawings/drawing" . $imageId . ".xml", $this->buildDrawingXML($imagePath, $imageId));
                $zip->addFromString("xl/drawings/_rels/drawing" . $imageId . ".xml.rels", $this->buildDrawingRelationshipXML());
            }
        }

        $zip->addEmptyDir("xl/worksheets/");
        $zip->addEmptyDir("xl/worksheets/_rels/");

        foreach ($this->sheets as $sheet) {
            $zip->addFromString("xl/worksheets/_rels/" . $sheet->xmlname . '.rels', $this->buildDrawingRelationshipXML());

            $zip->addFile($sheet->filename, "xl/worksheets/" . $sheet->xmlname);
        }

        $zip->addFromString("xl/workbook.xml", $this->buildWorkbookXML());
        $zip->addFile($this->writeStylesXML(), "xl/styles.xml");
        $zip->addFromString("[Content_Types].xml", $this->buildContentTypesXML());

        $zip->addEmptyDir("xl/_rels/");
        $zip->addFromString("xl/_rels/workbook.xml.rels", $this->buildWorkbookRelsXML());
        $zip->close();
    }

    /**
     * @param string $imagePath
     * @param int $imageId
     * @param int $startRowNum
     * @param int $endRowNum
     * @param int $startColNum
     * @param int $endColNum
     * @return string
     */
    public function buildDrawingXML($imagePath, $imageId, $startRowNum = 0, $endRowNum = 0, $startColNum = 0, $endColNum = 0)
    {
        $imageName = explode('/', $imagePath);
        $imageName = end($imageName);

        $imageRelationshipXML = '
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:dgm="http://schemas.openxmlformats.org/drawingml/2006/diagram">
                <xdr:twoCellAnchor>
                    <xdr:from>
                        <xdr:col>' . $startColNum . '</xdr:col>
                        <xdr:colOff>247650</xdr:colOff>
                        <xdr:row>' . $startRowNum . '</xdr:row>
                        <xdr:rowOff>76200</xdr:rowOff>
                    </xdr:from>
                    <xdr:to>
                        <xdr:col>' . $endColNum . '</xdr:col>
                        <xdr:colOff>381000</xdr:colOff>
                        <xdr:row>' . $endRowNum . '</xdr:row>
                        <xdr:rowOff>142875</xdr:rowOff>
                    </xdr:to>
                    <xdr:pic>
                        <xdr:nvPicPr>
                            <xdr:cNvPr id="0" name="' . $imageName . '" title="Image" />
                            <xdr:cNvPicPr preferRelativeResize="0" />
                        </xdr:nvPicPr>
                        <xdr:blipFill>
                            <a:blip cstate="print" r:embed="rId' . $imageId . '" />
                            <a:stretch>
                                <a:fillRect />
                            </a:stretch>
                        </xdr:blipFill>
                        <xdr:spPr>
                            <a:xfrm>
                                <a:ext cx="2419350" cy="790575" />
                            </a:xfrm>
                            <a:prstGeom prst="rect">
                                <a:avLst />
                            </a:prstGeom>
                            <a:noFill />
                        </xdr:spPr>
                    </xdr:pic>
                    <xdr:clientData fLocksWithSheet="0" />
                </xdr:twoCellAnchor>
            </xdr:wsDr>
        ';

        return $imageRelationshipXML;
    }

    /**
     * @return string
     */
    public function buildDrawingRelationshipXML()
    {
        $drawingXML = '<?xml version="1.0" encoding="UTF-8"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach ($this->images as $imageId => $imagePath) {
            $imageName = explode('/', $imagePath);
            $imageName = end($imageName);

            $drawingXML .= '<Relationship Id="rId' . $imageId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/' . $imageName . '"/>';
        }

        $drawingXML .= '</Relationships>';

        return $drawingXML;
    }

    /**
     * @return string
     */
    protected function buildContentTypesXML()
    {
        $content_types_xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $content_types_xml .= '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">';
        $content_types_xml .= '<Default ContentType="application/xml" Extension="xml"/>';
        $content_types_xml .= '<Default ContentType="image/png" Extension="png"/>';
        $content_types_xml .= '<Override PartName="/_rels/.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        $content_types_xml .= '<Override PartName="/xl/_rels/workbook.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>';
        foreach ($this->sheets as $sheet_name => $sheet) {
            $content_types_xml .= '<Override PartName="/xl/worksheets/' . ($sheet->xmlname) . '" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        if (count($this->images) > 0) {
            foreach ($this->images as $imageId => $imagePath) {
                $content_types_xml .= '<Override PartName="/xl/drawings/drawing' . $imageId . '.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml" />';
            }
        }
        $content_types_xml .= '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';
        $content_types_xml .= '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        $content_types_xml .= '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>';
        $content_types_xml .= '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
        $content_types_xml .= "\n";
        $content_types_xml .= '</Types>';

        return $content_types_xml;
    }

    /**
     * @param $sheet_name
     */
    protected function finalizeSheet($sheet_name)
    {
        if (empty($sheet_name) || $this->sheets[$sheet_name]->finalized)
            return;

        $sheet = &$this->sheets[$sheet_name];

        $sheet->file_writer->write('</sheetData>');

        if (!empty($sheet->merge_cells)) {
            $sheet->file_writer->write('<mergeCells>');
            foreach ($sheet->merge_cells as $range) {
                $sheet->file_writer->write('<mergeCell ref="' . $range . '"/>');
            }
            $sheet->file_writer->write('</mergeCells>');
        }

        if (count($this->images) > 0) {
            foreach ($this->images as $imageId => $imagePath) {
                $sheet->file_writer->write('<drawing r:id="rId' . $imageId . '" />');
            }
        }

        $sheet->file_writer->write('<printOptions headings="false" gridLines="false" gridLinesSet="true" horizontalCentered="false" verticalCentered="false"/>');
        $sheet->file_writer->write('<pageMargins left="0.5" right="0.5" top="1.0" bottom="1.0" header="0.5" footer="0.5"/>');
        $sheet->file_writer->write('<pageSetup blackAndWhite="false" cellComments="none" copies="1" draft="false" firstPageNumber="1" fitToHeight="1" fitToWidth="1" horizontalDpi="300" orientation="portrait" pageOrder="downThenOver" paperSize="1" scale="100" useFirstPageNumber="true" usePrinterDefaults="false" verticalDpi="300"/>');
        $sheet->file_writer->write('<headerFooter differentFirst="false" differentOddEven="false">');
        $sheet->file_writer->write('<oddHeader>&amp;C&amp;&quot;Times New Roman,Regular&quot;&amp;12&amp;A</oddHeader>');
        $sheet->file_writer->write('<oddFooter>&amp;C&amp;&quot;Times New Roman,Regular&quot;&amp;12Page &amp;P</oddFooter>');
        $sheet->file_writer->write('</headerFooter>');
        $sheet->file_writer->write('</worksheet>');

        $max_cell = $this->xlsCell($sheet->row_count - 1, count($sheet->columns) - 1);
        $max_cell_tag = '<dimension ref="A1:' . $max_cell . '"/>';
        $padding_length = $sheet->max_cell_tag_end - $sheet->max_cell_tag_start - strlen($max_cell_tag);
        $sheet->file_writer->fseek($sheet->max_cell_tag_start);
        $sheet->file_writer->write($max_cell_tag . str_repeat(" ", $padding_length));
        $sheet->file_writer->close();
        $sheet->finalized = true;
    }

    /**
     * @return string
     */
    protected function buildRelationshipsXML()
    {
        $lastRelationshipId = 0;

        $rels_xml = "";
        $rels_xml .= '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $rels_xml .= '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $rels_xml .= '<Relationship Id="rId' . (++$lastRelationshipId) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>';
        $rels_xml .= '<Relationship Id="rId' . (++$lastRelationshipId) . '" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>';
        $rels_xml .= '<Relationship Id="rId' . (++$lastRelationshipId) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>';

        if (count($this->images) > 0) {
            foreach ($this->images as $imageId => $imagePath) {
                $rels_xml .= '<Relationship Id="rId' . (++$lastRelationshipId) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="xl/drawings/drawing' . $imageId . '.xml" />';
            }
        }

        $rels_xml .= "\n";
        $rels_xml .= '</Relationships>';

        return $rels_xml;
    }
}