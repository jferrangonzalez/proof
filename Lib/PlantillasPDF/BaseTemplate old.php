<?php
/**
 * Copyright (C) 2019-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 */

namespace FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF;

use FacturaScripts\Core\Base\Calculator;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;
use FacturaScripts\Core\Base\ToolBox;
use FacturaScripts\Core\Model\Base\BusinessDocument;
use FacturaScripts\Core\Model\Base\BusinessDocumentLine;
use FacturaScripts\Dinamic\Model\AttachedFile;
use FacturaScripts\Dinamic\Model\Contacto;
use FacturaScripts\Dinamic\Model\CuentaBanco;
use FacturaScripts\Dinamic\Model\CuentaBancoCliente;
use FacturaScripts\Dinamic\Model\Empresa;
use FacturaScripts\Dinamic\Model\FormaPago;
use FacturaScripts\Dinamic\Model\FormatoDocumento;
use FacturaScripts\Dinamic\Model\Impuesto;
use FacturaScripts\Dinamic\Model\Pais;
use FacturaScripts\Dinamic\Model\ReciboCliente;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use Mpdf\QrCode\Output;
use Mpdf\QrCode\QrCode;

/**
 * Description of BaseTemplate
 *
 * @author Carlos Garcia Gomez <carlos@facturascripts.com>
 */
abstract class BaseTemplate
{

    const DEFAULT_LOGO = 'Dinamic/Assets/Images/logo-100.png';
    const MAX_IMAGE_FILE_SIZE = 2048000;
    const MEGACITY20_LOGO = 'Plugins/MC20Instance/Assets/Images/logo-100.png';

    /**
     * @var string
     */
    public $body = '';

    /**
     * @var string
     */
    protected $config = [];

    /**
     * @var Empresa
     */
    protected $empresa;

    /**
     * @var array
     */
    protected $fixedBlocks = [];

    /**
     * @var FormatoDocumento
     */
    public $format;

    /**
     * @var BusinessDocument
     */
    public $headerModel;

    /**
     * @var string
     */
    protected $imagetextPath;

    /**
     * @var string
     */
    protected $imagefooterPath;

    /**
     * @var bool
     */
    public $initHtml = false;

    /**
     * @var bool
     */
    public $isBusinessDoc = false;

    /**
     * @var string
     */
    protected $logoPath;

    /**
     * @var MPDF
     */
    public $mpdf = null;

    /**
     * @var string
     */
    protected $qrFilePath;

    /**
     * @var bool
     */
    protected $showHeaderTitle = true;

    abstract public function addInvoiceFooter($model);

    abstract public function addInvoiceHeader($model);

    abstract public function addInvoiceLines($model);

    public function __construct()
    {
        // logo
        $this->logoPath = file_exists(self::MEGACITY20_LOGO) ? self::MEGACITY20_LOGO : self::DEFAULT_LOGO;
        $this->setImage('logoPath', $this->get('idlogo'));

        $this->empresa = new Empresa();
        $this->empresa->loadFromCode($this->toolBox()->appSettings()->get('default', 'idempresa'));
        $this->setImage('logoPath', $this->empresa->idlogo);

        $this->setImage('imagetextPath', $this->get('idimagetext'));
        $this->setImage('imagefooterPath', $this->get('idimagefooter'));
    }

    /**
     * @param array $data
     * @throws MpdfException
     */
    public function addDualColumnTable($data)
    {
        $html = '';
        $num = 0;
        foreach ($data as $row) {
            if ($num === 0) {
                $html .= '<tr>';
            } elseif ($num % 2 == 0) {
                $html .= '</tr><tr>';
            }

            $html .= '<td width="50%"><b>' . $row['title'] . '</b>: ' . $row['value'] . '</td>';

            $num++;
        }

        $html .= '</tr>';
        $this->writeHTML('<table class="table-big table-dual">' . $html . '</table><br/>');
    }

    /**
     * @param array $rows
     * @param array $titles
     * @param array $alignments
     * @throws MpdfException
     */



    public function addTable($rows, $titles, $alignments)
    {
        $html = '<thead><tr>';
        foreach ($titles as $key => $title) {
            $html .= isset($alignments[$key]) ?
                '<th align="' . $alignments[$key] . '">' . $title . '</th>' :
                '<th>' . $title . '</th>';
        }
        $html .= '</tr></thead>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $key => $cell) {
                $html .= isset($alignments[$key]) ?
                    '<td align="' . $alignments[$key] . '">' . $cell . '</td>' :
                    '<td>' . $cell . '</td>';
            }
            $html .= '</tr>';
        }

        $this->writeHTML('<table class="table-big table-list">' . $html . '</table><br/>');
    }

    public function initHtml()
    {
        if ($this->initHtml === false) {
            $html = '<html>'
                . '<head>'
                . '<title>' . $this->get('title') . '</title>'
                . '<style>' . $this->css() . '</style>'
                . '</head>'
                . '<body>' . $this->body;
            $this->writeHTML($html);
            $this->initHtml = true;
        }
    }

    public function initMpdf()
    {
        $this->createQR();

        if (is_null($this->mpdf)) {
            $orientation = strtolower(substr($this->get('orientation'), 0, 1)) === 'l' ? 'L' : 'P';

            $config = [
                'format' => $this->get('size') . '-' . $orientation,
                'margin_top' => $this->get('topmargin'),
                'margin_bottom' => $this->get('bottommargin'),
                'tempDir' => FS_FOLDER . '/MyFiles/Cache'
            ];

            $this->mpdf = new Mpdf($config);
            $this->mpdf->SetCreator('FacturaScripts');

            $password = $this->get('password');
            if (!empty($password)) {
                $this->mpdf->SetProtection(['copy', 'print', 'print-highres'], null, $password, 128);
            }
        }
    }

    /**
     * @param string $fileName
     *
     * @return string
     * @throws MpdfException
     */
    public function output(string $fileName = ''): string
    {
        if (null === $this->mpdf) {
            $this->initMpdf();
            $this->initHtml();
        }

        foreach ($this->fixedBlocks as $block) {
            $this->mpdf->WriteFixedPosHTML($block['html'], $block['x'], $block['y'], $block['w'], $block['h']);
        }

        $this->writeHTML('</body></html>');
        return $this->mpdf->Output($fileName, Destination::STRING_RETURN);
    }

    /**
     * @param int $idempresa
     */
    public function setEmpresa($idempresa)
    {
        if ($idempresa != $this->empresa->idempresa) {
            $this->empresa->loadFromCode($idempresa);
            $this->setImage('logoPath', $this->empresa->idlogo);
        }
    }

    /**
     * @param FormatoDocumento $format
     */
    public function setFormat($format)
    {
        $this->format = $format;

        $optionalFields = [
            'color1', 'linecolalignments', 'linecols', 'linecoltypes', 'orientation', 'size'
        ];
        foreach ($optionalFields as $field) {
            if ($format->{$field}) {
                $this->config[$field] = $format->{$field};
            }
        }

        $fields = ['footertext', 'linesheight', 'thankstext', 'thankstitle'];
        foreach ($fields as $field) {
            $this->config[$field] = $format->{$field};
        }

        if ($format->texto) {
            $this->config['endtext'] = $format->texto;
        }

        if ($format->idlogo) {
            $this->setImage('logoPath', $format->idlogo);
        }

        if ($format->idimagetext) {
            $this->setImage('imagetextPath', $format->idimagetext);
        }

        if ($format->idimagefooter) {
            $this->setImage('imagefooterPath', $format->idimagefooter);
        }
    }

    /**
     * @param string $title
     * @param bool $force
     */
    public function setHeaderTitle($title, bool $force = false)
    {
        if (empty($this->config['headertitle']) || $force) {
            $this->config['headertitle'] = $title;
        }
    }

    public function setImage($var, $idfile)
    {
        $atFile = new AttachedFile();
        if ($idfile && $atFile->loadFromCode($idfile) && $atFile->size <= static::MAX_IMAGE_FILE_SIZE) {
            $this->{$var} = FS_FOLDER . '/' . $atFile->path;
        }
    }

    /**
     * @param string $value
     */
    public function setOrientation($value)
    {
        $this->config['orientation'] = $value;
    }

    /**
     * @param string $title
     * @param bool $force
     */
    public function setTitle($title, bool $force = false)
    {
        if (empty($this->config['title']) || $force) {
            $this->config['title'] = $title;
        }
    }

    /**
     * @param string $html
     * @param float $x
     * @param float $y
     * @param float $w
     * @param float $h
     */
    public function writeFixedPosHTML(string $html, $x, $y, $w, $h)
    {
        $this->fixedBlocks[] = [
            'html' => $html,
            'x' => $x,
            'y' => $y,
            'w' => $w,
            'h' => $h
        ];
    }

    /**
     * @param string $html
     * @throws MpdfException
     */
    public function writeHTML(string $html)
    {
        $this->mpdf->SetHTMLHeader($this->header(), 'O', true);
        $this->mpdf->SetHTMLFooter($this->footer(), 'O', true);

        $this->body .= $html;
        $this->mpdf->WriteHTML($html);
    }

    protected function addPageBreak($currentY, $model)
    {
        $linesHeight = (float)$this->get('linesheight');
        if (empty($linesHeight)) {
            return;
        }

        $mm = (($linesHeight * 25.4) / 96) + $currentY;
        if ($this->mpdf->y <= $mm) {
            return;
        }

        if ('' !== $this->getObservations($model)) {
            $this->mpdf->AddPage();
            return;
        }

        if (method_exists($model, 'getReceipts') && empty($model->getReceipts())) {
            return;
        }

        if ($this->format->id && false === $this->format->hidetotals) {
            if (false === $this->format->hidepaymentmethods && false === $this->format->hidereceipts) {
                $this->mpdf->AddPage();
                return;
            }

            if (true === $this->format->hidepaymentmethods && false === $this->format->hidereceipts) {
                $this->mpdf->AddPage();
                return;
            }

            if (false === $this->format->hidepaymentmethods && true === $this->format->hidereceipts) {
                $this->mpdf->AddPage();
            }
            return;
        }

        // si no hay formato
        if (false === $this->get('hidepaymentmethods') && false === $this->get('hidereceipts')) {
            $this->mpdf->AddPage();
            return;
        }

        if (true === $this->get('hidepaymentmethods') && false === $this->get('hidereceipts')) {
            $this->mpdf->AddPage();
            return;
        }

        if (false === $this->get('hidepaymentmethods') && true === $this->get('hidereceipts')) {
            $this->mpdf->AddPage();
        }
    }

    protected function autoHideLineColumns(array $lines)
    {
        $alignments = [];
        $cols = [];
        $types = [];
        foreach ($this->getInvoiceLineFields() as $key => $field) {
            $show = false;
            foreach ($lines as $line) {
                if (isset($line->{$key}) && $line->{$key} || $key === 'totaliva' || $key === 'precioiva' || $key === 'numlinea') {
                    $show = true;
                    break;
                }
            }

            if ($show) {
                $cols[] = $key;
                $alignments[] = $field['align'];
                $types[] = $field['type'];
            }
        }

        $this->config['linecols'] = implode(',', $cols);
        $this->config['linecolalignments'] = implode(',', $alignments);
        $this->config['linecoltypes'] = implode(',', $types);
    }

    protected function createQR()
    {
        if (empty($this->headerModel)) {
            return;
        }

        $filename = $this->headerModel->codigo . '_' . $this->get('qrfield') . '.png';
        $folderPath = FS_FOLDER . '/MyFiles/QRcode/';
        $this->qrFilePath = $folderPath . $filename;

        if (false === is_dir($folderPath)) {
            // dir doesn't exist, make it
            mkdir($folderPath, 0777, true);
        }

        switch ($this->get('qrfield')) {
            case 'ticketbai':
                return $this->createQRTicketbai();

            default:
                return $this->createQRDefault();
        }
    }

    protected function createQRDefault(string $value = '')
    {
        if (empty($value) && (false === isset($this->headerModel->{$this->get('qrfield')}) || empty($this->headerModel->{$this->get('qrfield')}))) {
            return;
        } elseif (empty($value) && isset($this->headerModel->{$this->get('qrfield')})) {
            $value = $this->headerModel->{$this->get('qrfield')};
        }

        if (empty($value)) {
            return;
        }

        // creamos el qr normal
        $qrCode = new QrCode($value, 'M');
        $qrCode->disableBorder();
        $qrcolor = $this->get('qrcolor') ?? '#000000';
        $qrbgcolor = $this->get('qrbgcolor') ?? '#FFFFFF';
        list($rColor, $gColor, $bColor) = sscanf($qrcolor, "#%02x%02x%02x");
        list($rBgColor, $gBgColor, $bBgColor) = sscanf($qrbgcolor, "#%02x%02x%02x");
        $qrsize = $this->get('qrsize') ?? 75;

        $output = new Output\Png();
        $data = $output->output($qrCode, $qrsize, [$rBgColor, $gBgColor, $bBgColor], [$rColor, $gColor, $bColor]);

        // guardamos el qr
        if (false === file_put_contents($this->qrFilePath, $data)) {
            return;
        }

        if ($this->get('qrtransparent')) {
            // si el modo transparencia está habilitado eliminamos el fondo
            $im = imagecreatefrompng($this->qrFilePath);
            $rmBgColor = imagecolorallocate($im, $rBgColor, $gBgColor, $bBgColor);
            imagecolortransparent($im, $rmBgColor);

            // actualizamos la imagen
            imagepng($im, $this->qrFilePath);

            // destruimos la imagen
            imagedestroy($im);
        }
    }

    protected function createQRTicketbai()
    {
        if (false === isset($this->headerModel->tbaiurl) || false === isset($this->headerModel->tbaicodbar)
            || empty($this->headerModel->tbaiurl) || empty($this->headerModel->tbaicodbar)) {
            return;
        }

        $this->createQRDefault($this->headerModel->tbaiurl);

        if (false === file_exists($this->qrFilePath)) {
            return;
        }

        $qrcolor = $this->get('qrcolor') ?? '#000000';
        $qrbgcolor = $this->get('qrbgcolor') ?? '#FFFFFF';
        list($rColor, $gColor, $bColor) = sscanf($qrcolor, "#%02x%02x%02x");
        list($rBgColor, $gBgColor, $bBgColor) = sscanf($qrbgcolor, "#%02x%02x%02x");

        // recuperamos la imagen del qr
        $img = imagecreatefrompng($this->qrFilePath);
        $qrsize = getimagesize($this->qrFilePath);

        $size = ($this->get('fontsize') * 3) / 4;
        $font = FS_FOLDER . '/Plugins/PlantillasPDF/vendor/mpdf/mpdf/ttfonts/' . $this->get('font') . '.ttf';
        // creamos una simulación del texto
        $txt_space = imagettfbbox($size, 0, $font, $this->headerModel->tbaicodbar);
        // obtenemos ancho y alto del texto
        $txt_width = abs($txt_space[4] - $txt_space[0]);
        $text_height = abs($txt_space[5] - $txt_space[1]);

        // creamos una imagen base donde unir el qr y el texto
        $base_width = max($txt_width, $qrsize[0]);
        $base_height = $qrsize[0] + $text_height;
        $baseimagen = Imagecreatetruecolor($base_width, $base_height);
        $bgcolor = imagecolorallocate($baseimagen, $rBgColor, $gBgColor, $bBgColor);
        imagefill($baseimagen, 0, 0, $bgcolor);
        $base_width = imagesx($baseimagen);

        if ($this->get('qrtransparent')) {
            // si el modo transparencia está habilitado eliminamos el fondo de la base
            imagesavealpha($baseimagen, true);
            $trans_background = imagecolorallocatealpha($baseimagen, $rBgColor, $gBgColor, $bBgColor, 127);
            imagefill($baseimagen, 0, 0, $trans_background);
        }

        // añadimos y centramos el qr en la base
        $centerQR = abs($base_width - $qrsize[0]) / 2;
        imagecopy($baseimagen, $img, $centerQR, 0, 0, 0, $qrsize[0], $qrsize[0]);

        // añadimos y centramos el texto en la base
        $centerText = abs($base_width - $txt_width) / 2;
        $textColor = imagecolorallocate($baseimagen, $rColor, $gColor, $bColor);
        imagettftext($baseimagen, $size, 0, $centerText, $qrsize[0] + $text_height, $textColor, $font, $this->headerModel->tbaicodbar);

        // actualizamos la imagen
        imagepng($baseimagen, $this->qrFilePath);

        // destruimos las imágenes
        imagedestroy($img);
        imagedestroy($baseimagen);
    }

    /**
     * @param BusinessDocument|Contacto $model
     * @param bool $shipping
     *
     * @return string
     */
    protected function combineAddress($model, bool $shipping = false): string
    {
        if (!isset($model->direccion)) {
            return '';
        }

        $completeAddress = '';
        $utils = $this->toolBox()->utils();
        if ($shipping && $model->nombre) {
            $completeAddress .= $utils->fixHtml($model->nombre) . ' ' . $utils->fixHtml($model->apellidos) . '<br>';
        }
        $completeAddress .= $utils->fixHtml($model->direccion);
        $completeAddress .= empty($model->apartado) ? '' : ', ' . $this->toolBox()->i18n()->trans('box') . ' ' . $model->apartado;
        $completeAddress .= empty($model->codpostal) ? '' : '<br/>' . $model->codpostal;
        $completeAddress .= empty($model->ciudad) ? '' : ', ' . $utils->fixHtml($model->ciudad);
        $completeAddress .= empty($model->provincia) ? '' : ' (' . $utils->fixHtml($model->provincia) . ')';
        $completeAddress .= empty($model->codpais) ? '' : ', ' . $this->getCountryName($model->codpais);

        // ¿Añadimos los teléfonos?
        $strPhones = property_exists($model, 'telefono1') ? $this->getPhones($model->telefono1, $model->telefono2) : '';
        if ($shipping && $this->get('showcustomerphones') && false === empty($strPhones)) {
            $completeAddress .= '<br/>' . $strPhones;
        }

        return $completeAddress;
    }

    protected function css(): string
    {
        return 'body {color: ' . $this->get('fontcolor') . '; font-family: ' . $this->get('font') . '; font-size: ' . $this->get('fontsize') . 'px;}'
            . '.font-big {font-size: ' . (2 + $this->get('fontsize')) . 'px;}'
            . '.m2 {margin: 2px;}'
            . '.m3 {margin: 3px;}'
            . '.m4 {margin: 4px;}'
            . '.m5 {margin: 5px;}'
            . '.m10 {margin: 10px;}'
            . '.mt-5 {margin-top: 5px;}'
            . '.mb-0 {margin-bottom: 0px;}'
            . '.p2 {padding: 2px;}'
            . '.p3 {padding: 3px;}'
            . '.p4 {padding: 4px;}'
            . '.p5 {padding: 5px;}'
            . '.p10 {padding: 10px;}'
            . '.spacer {font-size: 8px;}'
            . '.text-center {text-align: center;}'
            . '.text-left {text-align: left;}'
            . '.text-right {text-align: right;}'
            . '.border1 {border: 1px solid ' . $this->get('color1') . ';}'
            . '.no-border {border: 0px;}'
            . '.primary-box {background-color: ' . $this->get('color1') . '; color: ' . $this->get('color2') . '; padding: 10px; '
            . 'text-transform: uppercase; font-size: ' . $this->get('titlefontsize') . 'px; font-weight: bold;}'
            . '.seccondary-box {background-color: ' . $this->get('color3') . '; padding: 10px; '
            . 'text-transform: uppercase; font-size: ' . $this->get('titlefontsize') . 'px; font-weight: bold;}'
            . '.title {color: ' . $this->get('color1') . '; font-size: ' . $this->get('titlefontsize') . 'px;}'
            . '.table-big {width: 100%;}'
            . '.table-lines {height: ' . $this->get('linesheight') . 'px;}'
            . '.end-text {font-size: ' . $this->get('endfontsize') . 'px; text-align: ' . $this->get('endalign') . ';}'
            . '.footer-text {font-size: ' . $this->get('footerfontsize') . 'px; text-align: ' . $this->get('footeralign') . ';}'
            . '.color-red {color: red;}'
            . '.rotate-90 {rotate: -90;}'
            . '.font-bold {font-weight: bold;}'
            . '.qrcode {color: red; background-color: blue; margin: 0; padding: 0;}';
    }

    protected function getImageText(): string
    {
        return empty($this->imagetextPath) ? '' : '<div class="imagetext"><img src="' . $this->imagetextPath . '" height="' . $this->get('imagetextsize') . '"/></div>';
    }

    protected function getImageFooter(): string
    {
        return empty($this->imagefooterPath) ? '' : '<div class="imagefooter"><img src="' . $this->imagefooterPath . '" height="' . $this->get('imagefootersize') . '"/></div>';
    }

    protected function footer(): string
    {
        $this->setQrCode();
        $html = $this->getImageFooter();
        $html .= empty($this->get('footertext')) ? '' : '<p class="footer-text">' . nl2br($this->get('footertext')) . '</p>';
        return $html;
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    protected function get($key)
    {
        if (!isset($this->config[$key])) {
            $this->config[$key] = $this->toolBox()->appSettings()->get('plantillaspdf', $key);
        }

        return $this->config[$key];
    }

    /**
     * @param ReciboCliente $receipt
     * @param array $receipts
     *
     * @return string
     */
    protected function getBankData($receipt, $receipts): string
    {
        // buscamos si la forma de pago es igual en todos los recibos
        $paymentEqual = 0;
        foreach ($receipts as $r) {
            if ($receipt->codpago == $r->codpago && $receipt->idrecibo != $receipts[0]->idrecibo) {
                $paymentEqual++;
            }
        }

        // si es igual en todos los recibos, mostramos la forma de pago solo en el 1º recibo
        if (count($receipts) > 0 && count($receipts) == $paymentEqual) {
            return '';
        }

        $payMethod = new FormaPago();
        if (false === $payMethod->loadFromCode($receipt->codpago)) {
            return '-';
        }

        $cuentaBcoCli = new CuentaBancoCliente();
        $where = [new DataBaseWhere('codcliente', $receipt->codcliente)];
        if ($payMethod->domiciliado && $cuentaBcoCli->loadFromCode('', $where, ['principal' => 'DESC'])) {
            $bankClient = $payMethod->descripcion
                . '<br/>' . $cuentaBcoCli->getIban(true, true);

            if (false === empty($cuentaBcoCli->swift)) {
                $bankClient .= '<br/>' . $this->toolBox()->i18n()->trans('swift') . ': ' . $cuentaBcoCli->swift;
            }

            return $bankClient;
        }

        $cuentaBco = new CuentaBanco();
        if (empty($payMethod->codcuentabanco) || false === $cuentaBco->loadFromCode($payMethod->codcuentabanco) || empty($cuentaBco->iban)) {
            return $payMethod->descripcion;
        }

        $bank = $payMethod->descripcion
            . '<br/>' . $this->toolBox()->i18n()->trans('iban') . ': '
            . $cuentaBco->getIban(true);

        if (false === empty($cuentaBco->swift)) {
            $bank .= '<br/>' . $this->toolBox()->i18n()->trans('swift') . ': ' . $cuentaBco->swift;
        }

        return $bank;
    }

    protected function getCountryName(string $code): string
    {
        if (empty($code)) {
            return '';
        }

        $country = new Pais();
        return $country->loadFromCode($code) ? $this->toolBox()->utils()->fixHtml($country->nombre) : '';
    }

    protected function getInvoiceLineFieldAlignment(int $num): string
    {
        $valid = ['left', 'right', 'center', 'justify'];
        foreach (explode(',', str_replace(' ', '', $this->get('linecolalignments'))) as $num2 => $value) {
            if ($num == $num2 && in_array($value, $valid)) {
                return $value;
            }
        }

        return 'left';
    }

    protected function getInvoiceLineFieldTitle(string $txt): string
    {
        $codes = [
            'cantidad' => 'quantity-abb',
            'descripcion' => 'description',
            'dtopor' => 'dto',
            'dtopor2' => 'dto-2',
            'iva' => 'tax-abb',
            'numlinea' => 'line',
            'precioiva' => 'price-tax-abb',
            'pvpunitario' => 'price',
            'pvptotal' => 'net',
            'recargo' => 're',
            'referencia' => 'reference',
            'totaliva' => 'total'
        ];

        return isset($codes[$txt]) ? $this->toolBox()->i18n()->trans($codes[$txt]) : $this->toolBox()->i18n()->trans($txt);
    }

    protected function getInvoiceLineFieldType(int $num): string
    {
        $valid = [
            'money', 'money0', 'money1', 'money2', 'money3', 'money4', 'money5',
            'number', 'number0', 'number1', 'number2', 'number3', 'number4', 'number5',
            'percentage', 'percentage0', 'percentage1', 'percentage2', 'percentage3', 'percentage4', 'percentage5',
            'text'
        ];
        foreach (explode(',', str_replace(' ', '', $this->get('linecoltypes'))) as $num2 => $value) {
            if ($num == $num2 && in_array($value, $valid)) {
                return $value;
            }
        }

        return 'text';
    }

    protected function getInvoiceLineFields(): array
    {
        $fields = [];
        foreach (explode(',', str_replace(' ', '', $this->get('linecols'))) as $num => $key) {
            $fields[$key] = [
                'align' => $this->getInvoiceLineFieldAlignment($num),
                'key' => $key,
                'title' => $this->getInvoiceLineFieldTitle($key),
                'type' => $this->getInvoiceLineFieldType($num)
            ];
        }

        return $fields;
    }

    protected function getInvoiceLineValue(BusinessDocumentLine $line, array $field): string
    {
        switch ($field['key']) {
            case 'cantidad':
                if (property_exists($line, 'mostrar_cantidad') && $line->mostrar_cantidad === false) {
                    return '';
                }
                $value = $line->cantidad;
                break;

            case 'numlinea':
                $value = $line->numlinea ?? 0;
                break;

            case 'precioiva':
                if (property_exists($line, 'mostrar_precio') && $line->mostrar_precio === false) {
                    return '';
                }
                $value = $line->pvpunitario + ($line->pvpunitario * $line->iva / 100);
                break;

            case 'totaliva':
                if (property_exists($line, 'mostrar_precio') && $line->mostrar_precio === false) {
                    return '';
                }
                $value = $line->pvptotal + ($line->pvptotal * $line->iva / 100);
                break;

            case 'descripcion':
                $classTrazabilidad = '\\FacturaScripts\\Dinamic\\Model\\ProductoLoteMovimiento';
                if (false === class_exists($classTrazabilidad)) {
                    $value = $line->{$field['key']};
                    break;
                }

                $movimientos = new $classTrazabilidad();
                $doc = $line->getDocument();
                $where = [
                    new DataBaseWhere('docid', $doc->primaryColumnValue()),
                    new DataBaseWhere('docmodel', $doc->modelClassName()),
                    new DataBaseWhere('documento', $doc->codigo),
                    new DataBaseWhere('idlinea', $line->idlinea),
                    new DataBaseWhere('referencia', $line->referencia)
                ];

                $lotes = [];
                foreach ($movimientos->all($where) as $mov) {
                    $lotes[] = $mov->numserie . ' (' . $mov->cantidad . ') ' . $mov->fecha;
                }

                $value = empty($lotes) ? $line->{$field['key']} :
                    $line->{$field['key']} . '<div><br/>' . $this->toolBox()->i18n()->trans('batch-serial-numbers')
                    . ': ' . implode(",\n", $lotes) . '</div>';
                break;

            case 'iva':
            case 'irpf':
            case 'recargo':
            case 'pvpunitario':
            case 'pvptotal':
                if (property_exists($line, 'mostrar_precio') && $line->mostrar_precio === false) {
                    return '';
                }
            // no break
            default:
                if (!isset($line->{$field['key']})) {
                    return '';
                }
                $value = $line->{$field['key']};
                break;
        }

        if (empty($value) && (!isset($line->cantidad) || empty($line->cantidad))) {
            return '&nbsp;';
        }

        switch ($field['type']) {
            case 'money':
                $txt = $this->toolBox()->coins()->format($value);
                return str_replace(' ', '&nbsp;', $txt);

            case 'money0':
                $txt = $this->toolBox()->coins()->format($value, 0);
                return str_replace(' ', '&nbsp;', $txt);

            case 'money1':
                $txt = $this->toolBox()->coins()->format($value, 1);
                return str_replace(' ', '&nbsp;', $txt);

            case 'money2':
                $txt = $this->toolBox()->coins()->format($value, 2);
                return str_replace(' ', '&nbsp;', $txt);

            case 'money3':
                $txt = $this->toolBox()->coins()->format($value, 3);
                return str_replace(' ', '&nbsp;', $txt);

            case 'money4':
                $txt = $this->toolBox()->coins()->format($value, 4);
                return str_replace(' ', '&nbsp;', $txt);

            case 'money5':
                $txt = $this->toolBox()->coins()->format($value, 5);
                return str_replace(' ', '&nbsp;', $txt);

            case 'number':
                $txt = $this->toolBox()->numbers()->format($value);
                return str_replace(' ', '&nbsp;', $txt);

            case 'number0':
                $txt = $this->toolBox()->numbers()->format($value, 0);
                return str_replace(' ', '&nbsp;', $txt);

            case 'number1':
                $txt = $this->toolBox()->numbers()->format($value, 1);
                return str_replace(' ', '&nbsp;', $txt);

            case 'number2':
                $txt = $this->toolBox()->numbers()->format($value, 2);
                return str_replace(' ', '&nbsp;', $txt);

            case 'number3':
                $txt = $this->toolBox()->numbers()->format($value, 3);
                return str_replace(' ', '&nbsp;', $txt);

            case 'number4':
                $txt = $this->toolBox()->numbers()->format($value, 4);
                return str_replace(' ', '&nbsp;', $txt);

            case 'number5':
                $txt = $this->toolBox()->numbers()->format($value, 5);
                return str_replace(' ', '&nbsp;', $txt);

            case 'percentage':
                return $this->toolBox()->numbers()->format($value) . '%';

            case 'percentage0':
                return $this->toolBox()->numbers()->format($value, 0) . '%';

            case 'percentage1':
                return $this->toolBox()->numbers()->format($value, 1) . '%';

            case 'percentage2':
                return $this->toolBox()->numbers()->format($value, 2) . '%';

            case 'percentage3':
                return $this->toolBox()->numbers()->format($value, 3) . '%';

            case 'percentage4':
                return $this->toolBox()->numbers()->format($value, 4) . '%';

            case 'percentage5':
                return $this->toolBox()->numbers()->format($value, 5) . '%';

            case 'text':
                return nl2br($value);
        }

        return $value;
    }

    /**
     * @param BusinessDocument $model
     * @param array $lines
     * @param string $class
     *
     * @return string
     */
    protected function getInvoiceTaxes($model, $lines, $class = 'table-big'): string
    {
        $rows = $this->getTaxesRows($model, $lines);
        if (empty($model->totaliva)) {
            return '';
        }

        $coins = $this->toolBox()->coins();
        $i18n = $this->toolBox()->i18n();
        $numbers = $this->toolBox()->numbers();

        $trs = '';
        foreach ($rows as $row) {
            $trs .= '<tr>'
                . '<td align="left">' . $row['tax'] . '</td>'
                . '<td align="center">' . $coins->format($row['taxbase']) . '</td>'
                . '<td align="center">' . $numbers->format($row['taxp']) . '%</td>'
                . '<td align="center">' . $coins->format($row['taxamount']) . '</td>';

            if (empty($model->totalrecargo)) {
                $trs .= '</tr>';
                continue;
            }

            $trs .= '<td align="center">' . (empty($row['taxsurchargep']) ? '-' : $numbers->format($row['taxsurchargep']) . '%') . '</td>'
                . '<td align="right">' . (empty($row['taxsurcharge']) ? '-' : $coins->format($row['taxsurcharge'])) . '</td>'
                . '</tr>';
        }

        if (empty($model->totalrecargo)) {
            return '<table class="' . $class . '">'
                . '<thead>'
                . '<tr>'
                . '<th align="left">' . $i18n->trans('tax') . '</th>'
                . '<th align="center">' . $i18n->trans('tax-base') . '</th>'
                . '<th align="center">' . $i18n->trans('percentage') . '</th>'
                . '<th align="center">' . $i18n->trans('amount') . '</th>'
                . '</tr>'
                . '</thead>'
                . $trs
                . '</table>';
        }

        return '<table class="' . $class . '">'
            . '<tr>'
            . '<th align="left">' . $i18n->trans('tax') . '</th>'
            . '<th align="center">' . $i18n->trans('tax-base') . '</th>'
            . '<th align="center">' . $i18n->trans('tax') . '</th>'
            . '<th align="center">' . $i18n->trans('amount') . '</th>'
            . '<th align="center">' . $i18n->trans('re') . '</th>'
            . '<th align="right">' . $i18n->trans('amount') . '</th>'
            . '</tr>'
            . $trs
            . '</table>';
    }

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function getObservations($model)
    {
        return $this->get('hideobservations') ? '' : nl2br($model->observaciones);
    }

    protected function getPhones(?string $phone1 = '', ?string $phone2 = ''): string
    {
        $phone1 = str_replace(' ', '', $phone1);
        $phone2 = str_replace(' ', '', $phone2);

        if (empty($phone1) && empty($phone2)) {
            return '';
        } elseif (false === empty($phone1) && empty($phone2)) {
            return $this->toolBox()->i18n()->trans('phone') . ': ' . $phone1;
        } elseif (false === empty($phone2) && empty($phone1)) {
            return $this->toolBox()->i18n()->trans('phone') . ': ' . $phone2;
        }

        return $this->toolBox()->i18n()->trans('phones') . ': ' . $phone1 . ' - ' . $phone2;
    }

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function getSubjectIdFiscalStr($model)
    {
        return empty($model->cifnif) ? '' : $model->getSubject()->tipoidfiscal . ': ' . $model->cifnif;
    }

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function getSubjectName($model)
    {
        return $model->nombrecliente ?? $model->nombre;
    }

    /**
     * @param BusinessDocument $model
     *
     * @return string
     */
    protected function getSubjectTitle($model)
    {
        return isset($model->nombrecliente) ? $this->toolBox()->i18n()->trans('customer') : $this->toolBox()->i18n()->trans('supplier');
    }

    /**
     * @param BusinessDocument $model
     */
    protected function getTaxesRows($model, $lines)
    {
        // calculate total discount
        $totalDto = 1.0;
        foreach ([$model->dtopor1, $model->dtopor2] as $dto) {
            $totalDto *= 1 - $dto / 100;
        }

        $subtotals = [];
        foreach ($lines as $line) {
            $pvptotal = $line->pvptotal * $totalDto;
            if (empty($pvptotal) || $line->suplido) {
                continue;
            }

            $key = $line->codimpuesto . '_' . $line->iva . '_' . $line->recargo;
            if (!isset($subtotals[$key])) {
                $subtotals[$key] = [
                    'tax' => $key,
                    'taxbase' => 0,
                    'taxp' => $line->iva,
                    'taxamount' => 0,
                    'taxsurchargep' => $line->recargo,
                    'taxsurcharge' => 0
                ];

                $impuesto = new Impuesto();
                if ($line->codimpuesto && $impuesto->loadFromCode($line->codimpuesto)) {
                    $subtotals[$key]['tax'] = $impuesto->descripcion;
                }
            }

            $subtotals[$key]['taxbase'] += $pvptotal;
            $subtotals[$key]['taxamount'] += $pvptotal * $line->iva / 100;
            $subtotals[$key]['taxsurcharge'] += $pvptotal * $line->recargo / 100;
        }

        // irpf
        foreach ($lines as $line) {
            if (empty($line->irpf)) {
                continue;
            }

            $key = 'irpf_' . $line->irpf;
            if (!isset($subtotals[$key])) {
                $subtotals[$key] = [
                    'tax' => $this->toolBox()->i18n()->trans('irpf') . ' ' . $line->irpf . '%',
                    'taxbase' => 0,
                    'taxp' => $line->irpf,
                    'taxamount' => 0,
                    'taxsurchargep' => 0,
                    'taxsurcharge' => 0
                ];
            }

            $subtotals[$key]['taxbase'] += $line->pvptotal * $totalDto;
            $subtotals[$key]['taxamount'] -= $line->pvptotal * $totalDto * $line->irpf / 100;
        }

        // round
        foreach ($subtotals as $key => $value) {
            $subtotals[$key]['taxbase'] = round($value['taxbase'], FS_NF0);
            $subtotals[$key]['taxamount'] = round($value['taxamount'], FS_NF0);
            $subtotals[$key]['taxsurcharge'] = round($value['taxsurcharge'], FS_NF0);
        }

        return $subtotals;
    }

    protected function getTotalsModel(&$model, $lines)
    {
        $subtotals = Calculator::getSubtotals($model, $lines);
        $model->netosindto = $subtotals['netosindto'];
        $model->neto = $subtotals['neto'];
        $model->totaliva = $subtotals['totaliva'];
        $model->totalrecargo = $subtotals['totalrecargo'];
        $model->totalirpf = $subtotals['totalirpf'];
        $model->totalsuplidos = $subtotals['totalsuplidos'];
        $model->total = $subtotals['total'];
    }

    protected function header(): string
    {
        switch ($this->get('logoalign')) {
            case 'center':
                return $this->headerCenter();

            case 'full-size':
                return $this->headerFull();

            case 'right':
                return $this->headerRight();
        }

        // logo align left
        return $this->headerLeft();
    }

    protected function headerCenter(): string
    {
        $contactData = [];
        foreach (['web', 'email', 'telefono1', 'telefono2'] as $field) {
            if ($this->empresa->{$field}) {
                $contactData[] = $this->empresa->{$field};
            }
        }

        $title = $this->showHeaderTitle ? '<h1 class="mb-0 title text-center no-border">' . $this->get('headertitle') . '</h1>' : '';
        if ($this->isSketchInvoice()) {
            $title .= '<div class="color-red font-big font-bold text-center">' . $this->toolBox()->i18n()->trans('invoice-is-sketch') . '</div>';
        }

        return '<table class="table-big">'
            . '<tr>'
            . '<td valign="top" width="35%">'
            . '<p><b>' . $this->empresa->nombre . '</b>'
            . '<br/>' . $this->empresa->tipoidfiscal . ': ' . $this->empresa->cifnif
            . '<br/>' . $this->combineAddress($this->empresa) . '</p>'
            . '</td>'
            . '<td align="center" valign="top">'
            . '<img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>'
            . '</td>'
            . '<td align="right" valign="top" width="35%">'
            . '<p>' . implode('<br/>', $contactData) . '</p>'
            . '</td>'
            . '</tr>'
            . '</table>' . $title;
    }

    protected function headerFull(): string
    {
        $html = '<div class="text-center">'
            . '<img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>';

        if ($this->isSketchInvoice()) {
            $html .= '<div class="mt-5 color-red font-big font-bold">' . $this->toolBox()->i18n()->trans('invoice-is-sketch') . '</div>';
        }

        $html .= '</div>';
        return $html;
    }

    protected function headerLeft(): string
    {
        $contactData = [];
        foreach (['telefono1', 'telefono2', 'email', 'web'] as $field) {
            if ($this->empresa->{$field}) {
                $contactData[] = $this->empresa->{$field};
            }
        }

        $title = $this->showHeaderTitle ? '<h1 class="title">' . $this->get('headertitle') . '</h1>' . $this->spacer() : '';
        if ($this->isSketchInvoice()) {
            $title .= '<div class="color-red font-big font-bold">' . $this->toolBox()->i18n()->trans('invoice-is-sketch') . '</div>';
        }

        return '<table class="table-big">'
            . '<tr>'
            . '<td valign="top"><img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>' . '</td>'
            . '<td align="right" valign="top">' . $title
            . '<p><b>' . $this->empresa->nombre . '</b>'
            . '<br/>' . $this->empresa->tipoidfiscal . ': ' . $this->empresa->cifnif
            . '<br/>' . $this->combineAddress($this->empresa) . '</p>' . $this->spacer()
            . '<p>' . implode(' · ', $contactData) . '</p>'
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

        $title = $this->showHeaderTitle ? '<h1 class="title">' . $this->get('headertitle') . '</h1>' . $this->spacer() : '';
        if ($this->isSketchInvoice()) {
            $title .= '<div class="color-red font-big font-bold">' . $this->toolBox()->i18n()->trans('invoice-is-sketch') . '</div>';
        }

        return '<table class="table-big">'
            . '<tr>'
            . '<td>' . $title
            . '<p><b>' . $this->empresa->nombre . '</b>'
            . '<br/>' . $this->empresa->tipoidfiscal . ': ' . $this->empresa->cifnif
            . '<br/>' . $this->combineAddress($this->empresa) . '</p>' . $this->spacer()
            . '<p>' . implode(' · ', $contactData) . '</p>'
            . '</td>'
            . '<td align="right"><img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/></td>'
            . '</tr>'
            . '</table>';
    }

    protected function isSketchInvoice(): bool
    {
        if ($this->get('showinvoicesketch') && $this->headerModel && $this->headerModel->editable &&
            in_array($this->headerModel->modelClassName(), ['FacturaCliente', 'FacturaProveedor'])) {
            return true;
        }

        return false;
    }

    protected function setQrCode()
    {
        if (file_exists($this->qrFilePath)) {
            $this->mpdf->WriteFixedPosHTML('<img src="' . $this->qrFilePath . '">', $this->get('qrpositionx'), $this->get('qrpositiony'), $this->get('qrsize'), $this->get('qrsize'));
        }
    }

    protected function spacer(int $num = 1): string
    {
        $html = '';
        while ($num > 0) {
            $html .= '<div class="spacer">&nbsp;</div>';
            $num--;
        }

        return $html;
    }

    protected function toolBox(): ToolBox
    {
        return new ToolBox();
    }
}
