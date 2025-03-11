<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Lib\Export;

use FacturaScripts\Core\Lib\Export\ExportBase;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Model\Base\ModelClass;
use FacturaScripts\Dinamic\Model\FormatoDocumento;
use FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF\Template1;
use Mpdf\MpdfException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Description of PDFExport
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
class PDFExport extends ExportBase
{

    const LIST_LIMIT = 500;

    protected $newPage = false;

    /**
     * @var Template1
     */
    protected $template;

    /**
     * @param BusinessDocument $model
     *
     * @return bool
     */
    public function addBusinessDocPage($model): bool
    {
        if (null === $this->template->format) {
            $format = $this->getDocumentFormat($model);
            $this->template->setFormat($format);
        }

        $code = $this->template->format->primarynumero2 && isset($model->numero2) ? $model->numero2 : $model->primaryDescription();
        $modelTitle = $this->toolBox()->i18n()->trans($model->modelClassName() . '-min') . ' ' . $code;
        $title = empty($this->template->format->titulo) ? $modelTitle : $this->template->format->titulo . ' ' . $code;
        $this->template->setTitle($title, true);
        $this->template->setHeaderTitle($title, true);
        $this->template->setEmpresa($model->idempresa);

        $this->template->headerModel = $model;
        $this->template->isBusinessDoc = true;

        $this->template->initMpdf();
        $this->template->initHtml();

        $this->toolBox()->coins()->findDivisa($model);

        if ($this->newPage) {
            $this->template->mpdf->AddPage('', '', 1);
            $this->newPage = false;
        }

        $this->template->addInvoiceHeader($model);
        $this->template->addInvoiceLines($model);
        $this->template->addInvoiceFooter($model);
        $this->newPage = true;

        // do not continue with export
        return false;
    }

    /**
     * @param ModelClass $model
     * @param array $where
     * @param array $order
     * @param int $offset
     * @param array $columns
     * @param string $title
     *
     * @return bool
     * @throws MpdfException
     */
    public function addListModelPage($model, $where, $order, $offset, $columns, $title = ''): bool
    {
        $this->setFileName($title);
        $this->template->setHeaderTitle($title);

        $alignments = $this->getColumnAlignments($columns);
        $titles = $this->getColumnTitles($columns);

        if (count($titles) > 5) {
            $this->setOrientation('landscape');
        }

        $this->template->initMpdf();
        $this->template->initHtml();

        $cursor = $model->all($where, $order, $offset, self::LIST_LIMIT);
        if (empty($cursor)) {
            $this->template->addTable([], $titles, $alignments);
        }
        while (!empty($cursor)) {
            $rows = $this->getCursorData($cursor, $columns);
            $this->template->addTable($rows, $titles, $alignments);

            // Advance within the results
            $offset += self::LIST_LIMIT;
            $cursor = $model->all($where, $order, $offset, self::LIST_LIMIT);
        }

        return true;
    }

    /**
     * @param ModelClass $model
     * @param array $columns
     * @param string $title
     *
     * @return bool
     * @throws MpdfException
     */
    public function addModelPage($model, $columns, $title = ''): bool
    {
        $this->setFileName($title);
        if (isset($model->idempresa)) {
            $this->template->setEmpresa($model->idempresa);
        }
        $this->template->setHeaderTitle($title);

        $this->template->initMpdf();
        $this->template->initHtml();

        $data = $this->getModelColumnsData($model, $columns);
        $this->template->addDualColumnTable($data);
        return true;
    }

    /**
     * @param array $headers
     * @param array $rows
     * @param array $options
     * @param string $title
     *
     * @return bool
     * @throws MpdfException
     */
    public function addTablePage($headers, $rows, $options = [], $title = ''): bool
    {
        $this->template->initMpdf();
        $this->template->initHtml();

        $alignments = [];
        foreach (array_keys($headers) as $key) {
            if (array_key_exists($key, $options)) {
                $alignments[$key] = $options[$key]['display'] ?? 'left';
                continue;
            }
            $alignments[$key] = in_array($key, ['debe', 'haber', 'saldo', 'saldoprev']) ? 'right' : 'left';
        }

        // metemos cada 500 líneas en una tabla
        foreach (array_chunk($rows, self::LIST_LIMIT) as $lines) {
            $this->template->addTable($lines, $headers, $alignments);
        }

        return true;
    }

    /**
     * @return string
     * @throws MpdfException
     */
    public function getDoc()
    {
        return $this->template->output($this->getFileName() . '.pdf');
    }

    /**
     * @param string $title
     * @param int $idformat
     * @param string $langcode
     */
    public function newDoc(string $title, int $idformat, string $langcode)
    {
        $this->setFileName($title);
        $this->setTemplate();
        $this->template->setTitle($title);
        $this->template->setHeaderTitle($title);

        if (!empty($idformat)) {
            $format = new FormatoDocumento();
            $format->loadFromCode($idformat);
            $this->template->setFormat($format);
        }

        if (!empty($langcode)) {
            $this->toolBox()->i18n()->setDefaultLang($langcode);
        }
    }

    /**
     * @param string $orientation
     */
    public function setOrientation(string $orientation)
    {
        $this->template->setOrientation($orientation);
    }

    /**
     * @param Response $response
     * @throws MpdfException
     */
    public function show(Response &$response)
    {
        $response->headers->set('Content-type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'inline;filename=' . $this->getFileName() . '.pdf');
        $response->setContent($this->getDoc());
    }

    protected function setTemplate()
    {
        $name = $this->toolBox()->appSettings()->get('plantillaspdf', 'template', 'template1');
        $className = '\\FacturaScripts\\Dinamic\\Lib\\PlantillasPDF\\' . $name;
        if (false === class_exists($className)) {
            $className = '\\FacturaScripts\\Dinamic\\Lib\\PlantillasPDF\\Template1';
        }
        $this->template = new $className();
    }
}
