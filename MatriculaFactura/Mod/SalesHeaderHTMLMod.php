<?php

namespace FacturaScripts\Plugins\MatriculaFactura\Mod;

use FacturaScripts\Core\Base\Contract\SalesModInterface;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\Model\Base\SalesDocument;
use FacturaScripts\Core\Model\User;
use FacturaScripts\Core\Model\CodeModel;
use FacturaScripts\Core\Base\DataBase\DataBaseWhere;

class SalesHeaderHTMLMod implements SalesModInterface
{
    protected $codeModel;

    public function __construct()
    {
        $this->codeModel = new CodeModel();
    }

    public function apply(SalesDocument &$model, array $formData, User $user)
    {
        $model->matricula = $formData['matricula'] ?? null;
    }

    public function applyBefore(SalesDocument &$model, array $formData, User $user)
    {
        return true;
    }

    public function assets(): void
    {
        // No se requieren assets adicionales
    }

    public function newModalFields(): array
    {
        return [];
    }

    public function newBtnFields(): array
    {
        return [];
    }

    public function newFields(): array
    {
        return ['matricula'];
    }

    public function renderField(Translator $i18n, SalesDocument $model, string $field): ?string
    {
        if ($field === 'matricula') {
            return $this->matriculaColumn($i18n, $model);
        }
        return null;
    }

    private function matriculaColumn(Translator $i18n, SalesDocument $model): string
    {
        $disabled = $model->editable ? '' : ' disabled';
        if (in_array($model->modelClassName(), ['PedidoCliente', 'AlbaranCliente', 'FacturaCliente'])) {
            // Si ya hay un valor en el campo matricula, mostrar un input de texto no editable
            if (!empty($model->matricula)) {
                return '<div class="col-sm-2 matricula">'
                    . '<div class="form-group">'
                    . '<h6>Matrícula</h6>'
                    . '<input type="text" name="matricula" class="form-control" value="' . htmlspecialchars($model->matricula) . '" readonly>'
                    . '</div>'
                    . '</div>';
            }
    
            // Obtener codcliente del documento actual
            $codcliente = $model->codcliente;
            if (empty($codcliente)) {
                return '<div></div>';
            }
    
            // Crear la condición WHERE para filtrar por codcliente
            $where = [new DataBaseWhere('codcliente', $codcliente)];
          
            // Obtener las máquinas del cliente desde la tabla serviciosat_maquinas
            $maquinas = $this->codeModel->all('serviciosat_maquinas', 'idmaquina', 'nombre', false, $where);
    
            // Crear el select si no hay un valor en el campo matricula
            $select = '<select id="matriculaSelect" name="matricula" class="form-control"' . $disabled . '>';
            $select .= '<option value="">---</option>'; // Opción vacía por defecto
          
            // Añadir las opciones desde las máquinas encontradas
            foreach ($maquinas as $maquina) {
                $selected = ($model->matricula === $maquina->description) ? ' selected' : '';
                $select .= '<option value="' . htmlspecialchars($maquina->description) . '"' . $selected . '>' 
                        . $maquina->description . '</option>';
            }
            $select .= '</select>';
    
            return '<div class="col-sm-2 matricula">'
                . '<div class="form-group">'
                . '<h6>Matrícula</h6>'
                . $select
                . '</div>'
                . '</div>';
        }
        return '<div></div>';
    }
}