<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF;

use DeepCopy\DeepCopy;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Dinamic\Model\AgenciaTransporte;
use FacturaScripts\Dinamic\Model\Agente;
use FacturaScripts\Dinamic\Model\Cliente;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\Proveedor;

/**
 * Description of Template5
 *
 * @author Carlos Garcia Gomez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
class Template5 extends BaseTemplate
{
    /**
     * @param BusinessDocument $model
     */
    public function addInvoiceFooter($model)
    {
        $coins = $this->toolBox()->coins();
        $i18n = $this->toolBox()->i18n();
        $receipts = $model->modelClassName() === 'FacturaCliente' && !$this->get('hidereceipts') && !$this->format->hidetotals && !$this->format->hidereceipts
            ? $model->getReceipts() : [];

        if ($receipts) {
            $trs = '<thead>'
                . '<tr>'
                . '<th>' . $i18n->trans('receipt') . '</th>';
            if (!$this->get('hidepaymentmethods') && !$this->format->hidepaymentmethods) {
                $trs .= '<th>' . $i18n->trans('payment-method') . '</th>';
            }

            $trs .= '<th align="right">' . $i18n->trans('amount') . '</th>';
            if (!$this->get('hideexpirationpayment')) {
                $trs .= '<th align="right">' . $i18n->trans('expiration') . '</th>';
            }

            $trs .= '</tr>'
                . '</thead>';
            foreach ($receipts as $receipt) {
                $expiration = $receipt->pagado ? $i18n->trans('paid') : $receipt->vencimiento;
                $expiration .= $this->get('showpaymentdate') ? ' ' . $receipt->fechapago : '';

                $paylink = empty($receipt->url('pay')) ? '' : ' <a href="' . $receipt->url('pay') . '&mpdf=.html">' . $i18n->trans('pay') . '</a>';

                $trs .= '<tr>'
                    . '<td align="center">' . $receipt->numero . '</td>';
                if (!$this->get('hidepaymentmethods') && !$this->format->hidepaymentmethods) {
                    $trs .= '<td align="center">' . $this->getBankData($receipt, $receipts) . $paylink . '</td>';
                }

                $trs .= '<td align="right">' . $coins->format($receipt->importe) . '</td>';
                if (!$this->get('hideexpirationpayment')) {
                    $trs .= '<td align="right">' . $expiration . '</td>';
                }

                $trs .= '</tr>';
            }

            $this->writeHTML('<table class="table-big table-list">' . $trs . '</table>');
        } elseif (isset($model->codcliente) && false === $this->format->hidetotals && !$this->get('hidepaymentmethods') && !$this->format->hidepaymentmethods) {
            $expiration = isset($model->finoferta) ? $model->finoferta : '';
            $trs = '<thead>'
                . '<tr>'
                . '<th align="left">' . $i18n->trans('payment-method') . '</th>';

            if (!$this->get('hideexpirationpayment') && !$this->get('hidereceipts') && !$this->format->hidereceipts) {
                $trs .= '<th align="right">' . $i18n->trans('expiration') . '</th>';
            }

            $trs .= '</tr>'
                . '</thead>'
                . '<tr>'
                . '<td align="left">' . $this->getBankData($model, $receipts) . '</td>';
            if (!$this->get('hideexpirationpayment') && !$this->get('hidereceipts') && !$this->format->hidereceipts) {
                $trs .= '<td align="right">' . $expiration . '</td>';
            }

            $trs .= '</tr>';
            $this->writeHTML('<table class="table-big table-list">' . $trs . '</table>');
        }

        $this->writeHTML($this->getImageText());

        if (!empty($this->get('endtext'))) {
            $paragraph = '<p class="end-text">' . nl2br($this->get('endtext')) . '</p>';
            $this->writeHTML($paragraph);
        }
    }

    /**
     * @param BusinessDocument $model
     */
    public function addInvoiceHeader($model)
    {
        $html = $this->getInvoiceHeaderResume($model)
            . $this->getInvoiceHeaderShipping($model)
            . $this->getInvoiceHeaderBilling($model);
        $this->writeHTML('<table class="table-big data"><tr>' . $html . '</tr></table>');
    }

    /**
     * @param BusinessDocument $model
     */
    public function addInvoiceLines($model)
    {
        $lines = $model->getLines();
        $this->autoHideLineColumns($lines);

        $tHead = '<thead><tr>';
        foreach ($this->getInvoiceLineFields() as $field) {
            $tHead .= '<th align="' . $field['align'] . '">' . $field['title'] . '</th>';
        }
        $tHead .= '</tr></thead>';

        $tBody = '';
        $numlinea = 1;
        $tLines = [];
        foreach ($lines as $line) {
            $tLines[] = $line;
            $line->numlinea = $numlinea;
            $tBody .= '<tr>';
            foreach ($this->getInvoiceLineFields() as $field) {
                $tBody .= '<td align="' . $field['align'] . '" valign="top">' . $this->getInvoiceLineValue($line, $field) . '</td>';
            }
            $tBody .= '</tr>';
            $numlinea++;

            if (property_exists($line, 'salto_pagina') && $line->salto_pagina) {
                $this->writeHTML('<div class="table-lines"><table class="table-big table-list">' . $tHead . $tBody . '</table></div>');
                $this->writeHTML($this->getInvoiceTotals($model, $tLines));
                $this->mpdf->AddPage();
                $tBody = '';
                $tLines = [];
            }
        }

        $this->writeHTML('<div class="table-lines"><table class="table-big table-list">' . $tHead . $tBody . '</table></div>');

        $html2 = '';
        $observations = $this->getObservations($model);
        if (!empty($observations)) {
            $html2 .= '<p class="observations"><b>' . $this->toolBox()->i18n()->trans('observations') . '</b><br/>' . $observations . '</p>';
        }
        $html2 .= $this->getInvoiceTotals($model);

        // clonamos el documento y añadimos los totales para ver si salta de página
        $copier = new DeepCopy();
        $clonedPdf = $copier->copy($this->mpdf);
        $clonedPdf->writeHTML($html2);

        // comprobamos si clonedPdf tiene más páginas que el original
        if (count($clonedPdf->pages) > count($this->mpdf->pages)) {
            $this->mpdf->AddPage();
        }

        // si tiene las mismas páginas, añadimos los totales
        $this->writeHTML($html2);
    }

    protected function css(): string
    {
        return parent::css()
            . '.w-50 {width: 50%;}'
            . '.mb-5 {margin-bottom: 5px;}'
            . '.text-justify {text-align: justify;}'
            . '.header-top {background: ' . $this->get('color1') . '; padding: 25px; position: absolute; top: 0; left: 0;}'
            . '.logo-center .company {padding-top: 10px;}'
            . '.company {color: ' . $this->get('color2') . ';}'
            . '.data {margin-bottom: 15px; font-size: ' . $this->get('titlefontsize') . 'px;}'
            . '.table-list {margin-bottom: 15px; border-spacing: 0px; border-top: 1px solid ' . $this->get('color1') . '; border-bottom: 1px solid ' . $this->get('color1') . ';}'
            . '.table-list tr:nth-child(even) {background-color: ' . $this->get('color3') . ';}'
            . '.table-list th {background-color: ' . $this->get('color1') . '; color: ' . $this->get('color2') . '; padding: 5px; text-transform: uppercase;}'
            . '.table-list td {padding: 5px;}'
            . '.table-total {margin-bottom: 15px;}'
            . '.table-total tr:nth-child(even) {background-color: ' . $this->get('color3') . ';}'
            . '.table-total th {font-size: 16px; border-bottom: 1px solid ' . $this->get('color1') . '; color: ' . $this->get('color1') . '; text-transform: uppercase;}'
            . '.table-total td {font-size: 16px;}'
            . '.observations {margin-bottom: 15px;}'
            . '.observations b {font-size: 16px; color: ' . $this->get('color1') . ';}'
            . '.footer {position: absolute; bottom: 0; left: 0;}'
            . '.footer-table {padding: 10px; background: ' . $this->get('color1') . '; position: relative; bottom: 0; left: 0;}'
            . '.footer-text {color: ' . $this->get('color2') . ';}'
            . '.thanks-title {padding: 0 10px 0 10px; font-size: ' . $this->get('titlefontsize') . 'px; font-weight: bold; color: ' . $this->get('color1') . '; text-align: center;}'
            . '.thanks-text {padding: 0 10px 0 10px; text-align: center;}'
            . '.imagetext {margin-top: 15px; text-align: ' . $this->get('endalign') . ';}'
            . '.imagefooter {text-align: ' . $this->get('footeralign') . ';}';
    }

    protected function footer(): string
    {
        $this->setQrCode();
        $html = '<div class="footer">';
        $list = ['PresupuestoCliente', 'PedidoCliente', 'AlbaranCliente', 'FacturaCliente'];

        if ($this->headerModel && in_array($this->headerModel->modelClassName(), $list)) {
            $html .= empty($this->get('thankstitle')) ? '' : '<div class="thanks-title">' . $this->get('thankstitle') . '</div>'
                . '<div class="thanks-text">' . nl2br($this->get('thankstext')) . '</div>';

            if (!empty($this->get('thankstitle')) && !empty($this->get('footertext'))) {
                $html .= $this->spacer();
            }
        }

        $txt = empty($this->get('footertext')) ? '' : '<div class="footer-text">' . nl2br($this->get('footertext')) . '</div>';
        $html .= '<table class="footer-table table-big">'
            . '<tr>'
            . '<td class="text-' . $this->get('footeralign') . '">'
            . $this->getImageFooter()
            . $txt
            . '</td>'
            . '</tr>'
            . '</table>'
            . '</div>';

        return $html;
    }

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function getInvoiceHeaderBilling($model): string
    {
        $subject = $model->getSubject();
        $address = isset($model->codproveedor) && !isset($model->direccion) ? $subject->getDefaultAddress() : $model;
        $customerCode = $this->get('showcustomercode') ? $model->subjectColumnValue() : '';
        $customerEmail = $this->get('showcustomeremail') && !empty($subject->email) ? '<br>' . $this->toolBox()->i18n()->trans('email') . ': ' . $subject->email : '';
        $break = empty($model->cifnif) ? '' : '<br/>';
        return '<td valign="top">'
            . '<b>' . $this->getSubjectTitle($model) . '</b> ' . $customerCode
            . '<br/>' . $this->getSubjectName($model) . $break . $this->getSubjectIdFiscalStr($model)
            . '<br/>' . $this->combineAddress($address) . $this->getInvoiceHeaderBillingPhones($subject)
            . $customerEmail
            . '</td>';
    }

    /**
     * @param Cliente|Proveedor $subject
     *
     * @return string
     */
    protected function getInvoiceHeaderBillingPhones($subject): string
    {
        if (true !== $this->get('showcustomerphones')) {
            return '';
        }

        $strPhones = $this->getPhones($subject->telefono1, $subject->telefono2);
        if (empty($strPhones)) {
            return '';
        }

        return '<br/>' . $strPhones;
    }

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function getInvoiceHeaderResume($model): string
    {
        $modelTitle = $this->toolBox()->i18n()->trans($model->modelClassName() . '-min');
        $title = empty($this->format->titulo) ? $modelTitle : $this->format->titulo;
        $title = $this->showHeaderTitle ? '<b>' . $title . ':</b> ' . $model->codigo : '';

        // rectified invoice?
        $extra1 = '';
        if (isset($model->codigorect) && !empty($model->codigorect)) {
            $extra1 .= '<br/><b>' . $this->toolBox()->i18n()->trans('original') . ':</b> ' . $model->codigorect;
        }

        // number2?
        $extra2 = '';
        if (isset($model->numero2) && !empty($model->numero2) && (bool)$this->get('shownumero2')) {
            $extra2 .= '<br/><b>' . $this->toolBox()->i18n()->trans('number2') . ':</b> ' . $model->numero2;
        }

        // carrier or tracking-code?
        $extra3 = '';
        if (isset($model->codtrans) && !empty($model->codtrans)) {
            $carrier = new AgenciaTransporte();
            $carrierName = $carrier->loadFromCode($model->codtrans) ? $carrier->nombre : '-';
            $extra3 .= '<br/><b>' . $this->toolBox()->i18n()->trans('carrier') . ':</b> ' . $carrierName;
        }
        if (isset($model->codigoenv) && !empty($model->codigoenv)) {
            $extra3 .= '<br/><b>' . $this->toolBox()->i18n()->trans('tracking-code') . ':</b> ' . $model->codigoenv;
        }

        // agent?
        $extra4 = '';
        if (isset($model->codagente) && !empty($model->codagente) && (bool)$this->get('showagent')) {
            $agent = new Agente();
            $agent->loadFromCode($model->codagente);
            $extra4 .= '<br/><b>' . $this->toolBox()->i18n()->trans('agent') . ':</b> ' . $agent->nombre;
        }

        // project?
        $extra5 = '';
        $classProject = '\\FacturaScripts\\Dinamic\\Model\\Proyecto';
        if (isset($model->idproyecto) && !empty($model->idproyecto) && class_exists($classProject)) {
            $project = new $classProject();
            $project->loadFromCode($model->idproyecto);
            $extra5 .= '<br/><b>' . $this->toolBox()->i18n()->trans('project') . ':</b> ' . $project->nombre;
        }

        $extra6 = '';
        $classNCF = '\\FacturaScripts\\Dinamic\\Model\\NCFTipo';
        if (isset($model->numeroncf) && !empty($model->numeroncf) && class_exists($classNCF)) {
            $extra6 .= '<br/><b>' . $this->toolBox()->i18n()->trans('desc-ncf-number') . ':</b> ' . $model->numeroncf;
        }

        if (isset($model->tipocomprobante) && !empty($model->tipocomprobante) && class_exists($classNCF)) {
            $extra6 .= '<br/><b>' . $this->toolBox()->i18n()->trans('tipocomprobante') . ':</b> ' . $model->tipocomprobante;
        }

        return '<td valign="top">'
            . strtoupper($title)
            . '<br/><b>' . $this->toolBox()->i18n()->trans('date') . ':</b> ' . $model->fecha
            . $extra1
            . $extra2
            . $extra3
            . $extra4
            . $extra5
            . $extra6
            . '</td>';
    }

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function getInvoiceHeaderShipping($model): string
    {
        $contacto = new Contacto();
        if ($this->get('hideshipping') ||
            !isset($model->idcontactoenv) ||
            empty($model->idcontactoenv) ||
            $model->idcontactoenv == $model->idcontactofact ||
            false === $contacto->loadFromCode($model->idcontactoenv)) {
            return '';
        }

        return '<td valign="top"><b>' . $this->toolBox()->i18n()->trans('shipping-address') . '</b>'
            . '<br/>' . $this->combineAddress($contacto, true) . '</td>';
    }

    protected function getInvoiceTotals($model, $lines = []): string
    {
        if ($this->format->hidetotals) {
            return '';
        }

        $lines = empty($lines) ? $model->getLines() : $lines;
        $coins = $this->toolBox()->coins();
        $i18n = $this->toolBox()->i18n();
        $numbers = $this->toolBox()->numbers();
        $ths = '';
        $tds = '';
        $fields = [
            'netosindto' => $i18n->trans('subtotal'),
            'dtopor1' => $i18n->trans('global-dto'),
            'dtopor2' => $i18n->trans('global-dto-2'),
            'neto' => $i18n->trans('net'),
            'totaliva' => $i18n->trans('taxes'),
            'totalrecargo' => $i18n->trans('re'),
            'totalirpf' => $i18n->trans('irpf'),
            'totalsuplidos' => $i18n->trans('supplied-amount'),
            'total' => $i18n->trans('total')
        ];
        $this->getTotalsModel($model, $lines);

        foreach ($fields as $key => $title) {
            if (empty($model->{$key})) {
                continue;
            }

            switch ($key) {
                case 'dtopor1':
                case 'dtopor2':
                    $ths .= '<th align="right">' . $title . '</th>';
                    $tds .= '<td align="right">' . $numbers->format($model->{$key}) . '%</td>';
                    break;

                case 'total':
                    $ths .= '<th class="text-right">' . $title . '</th>';
                    $tds .= '<td class="font-big text-right"><b>' . $coins->format($model->{$key}) . '</b></td>';
                    break;

                case 'netosindto':
                    if ($model->netosindto == $model->neto) {
                        break;
                    }
                // no break

                default:
                    $ths .= '<th align="right">' . $title . '</th>';
                    $tds .= '<td align="right">' . $coins->format($model->{$key}) . '</td>';
                    break;
            }
        }

        $html = '<table class="table-big table-total">'
            . '<thead><tr>' . $ths . '</tr></thead>'
            . '<tr>' . $tds . '</tr>'
            . '</table>';

        $htmlTaxes = $this->getInvoiceTaxes($model, $lines, 'table-big table-list');
        if (!empty($htmlTaxes)) {
            $html .= '<br/>' . $htmlTaxes;
        }

        return $html;
    }

    protected function header(): string
    {
        $html = '<div class="header-top">';
        switch ($this->get('logoalign')) {
            case 'center':
                $html .= $this->headerCenter();
                break;

            case 'full-size':
                $html .= $this->headerFull();
                break;

            case 'right':
                $html .= $this->headerRight();
                break;

            default:
                $html .= $this->headerLeft();
                break;
        }
        $html .= '</div>';
        return $html;
    }

    protected function headerCenter(): string
    {
        $contactData = [];
        foreach (['telefono1', 'telefono2', 'email', 'web'] as $field) {
            if ($this->empresa->{$field}) {
                $contactData[] = $this->empresa->{$field};
            }
        }

        $warning = '';
        if ($this->isSketchInvoice()) {
            $warning .= '<div class="color-red font-big font-bold">' . $this->toolBox()->i18n()->trans('invoice-is-sketch') . '</div>';
        }

        return '<table class="table-big logo-center">'
            . '<tr>'
            . '<td class="logo text-center">'
            . '<img class="mb-5" src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>'
            . $warning
            . '</td>'
            . '</tr>'
            . '<tr>'
            . '<td class="company text-center">'
            . '<p><b>' . $this->empresa->nombre . '</b>'
            . '<br/>' . $this->empresa->tipoidfiscal . ': ' . $this->empresa->cifnif
            . '<br/>' . $this->combineAddress($this->empresa) . '</p>' . $this->spacer()
            . '<p>' . implode(' · ', $contactData) . '</p>'
            . '</td>'
            . '</tr>'
            . '</table>';
    }

    protected function headerFull(): string
    {
        $warning = '';
        if ($this->isSketchInvoice()) {
            $warning .= '<div class="color-red font-big font-bold">' . $this->toolBox()->i18n()->trans('invoice-is-sketch') . '</div>';
        }

        return '<table class="table-big logo-full">'
            . '<tr>'
            . '<td class="logo text-center">'
            . '<img class="mb-5" src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>'
            . $warning
            . '</td>'
            . '</tr>'
            . '</table>';
    }

    protected function headerLeft(): string
    {
        $contactData = [];
        foreach (['telefono1', 'telefono2', 'email', 'web'] as $field) {
            if ($this->empresa->{$field}) {
                $contactData[] = $this->empresa->{$field};
            }
        }

        $warning = '';
        if ($this->isSketchInvoice()) {
            $warning .= '<div class="color-red font-big font-bold">' . $this->toolBox()->i18n()->trans('invoice-is-sketch') . '</div>';
        }

        return '<table class="table-big logo-left">'
            . '<tr>'
            . '<td class="logo w-50">'
            . '<img class="mb-5" src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>'
            . $warning
            . '</td>'
            . '<td class="company w-50 text-right">'
            . '<b>' . $this->empresa->nombre . '</b>'
            . '<br/>' . $this->empresa->tipoidfiscal . ': ' . $this->empresa->cifnif
            . '<br/>' . $this->combineAddress($this->empresa) . $this->spacer() . implode(' · ', $contactData)
            . '</td>'
            . '</tr>'
            . '</table>';
    }

    protected function headerRight(): string
    {
        $contactData = [];
        foreach (['telefono1', 'telefono2', 'email', 'web'] as $field) {
            if ($this->empresa->{$field}) {
                $contactData[] = $this->empresa->{$field};
            }
        }

        $warning = '';
        if ($this->isSketchInvoice()) {
            $warning .= '<div class="color-red font-big font-bold">' . $this->toolBox()->i18n()->trans('invoice-is-sketch') . '</div>';
        }

        return '<table class="table-big logo-right">'
            . '<tr>'
            . '<td class="company w-50">'
            . '<p><b>' . $this->empresa->nombre . '</b>'
            . '<br/>' . $this->empresa->tipoidfiscal . ': ' . $this->empresa->cifnif
            . '<br/>' . $this->combineAddress($this->empresa) . '</p>' . $this->spacer()
            . '<p>' . implode(' · ', $contactData) . '</p>'
            . '</td>'
            . '<td class="logo w-50 text-right">'
            . '<img class="mb-5" src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>'
            . $warning
            . '</td>'
            . '</tr>'
            . '</table>';
    }
}