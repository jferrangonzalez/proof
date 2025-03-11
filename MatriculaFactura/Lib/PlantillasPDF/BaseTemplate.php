<?php
/**
 * Extensión de la clase BaseTemplate para añadir la matrícula del vehículo.
 *
 * @author José Ferrán
 */

namespace FacturaScripts\Plugins\MatriculaFactura\Lib\PlantillasPDF;

use FacturaScripts\Plugins\PlantillasPDF\Lib\PlantillasPDF\BaseTemplate as ParentClass;

abstract class BaseTemplate extends ParentClass
{
    /**
     * Sobrescribe el método headerCenter para añadir la matrícula.
     */
    protected function headerCenter(): string
    {
        // Obtenemos el HTML original de la clase padre
        $html = parent::headerCenter();

        // Añadimos la matrícula si existe
        if (property_exists($this->headerModel, 'matricula') && !empty($this->headerModel->matricula)) {
            // Buscamos el cierre del párrafo </p> donde queremos insertar la matrícula
            $searchStr = '</p>';
            $position = strpos($html, $searchStr);
            
            if ($position !== false) {
                $matriculaHtml = '<br/><b>' . $this->toolBox()->i18n()->trans('matricula') . ':</b> ' . $this->headerModel->matricula;
                $html = substr_replace($html, $matriculaHtml . $searchStr, $position, strlen($searchStr));
            }
        }

        return $html;
    }

    /**
     * Sobrescribe el método headerLeft para añadir la matrícula.
     */
    protected function headerLeft(): string
    {
        // Obtenemos el HTML original de la clase padre
        $html = parent::headerLeft();

        // Añadimos la matrícula si existe
        if (property_exists($this->headerModel, 'matricula') && !empty($this->headerModel->matricula)) {
            $searchStr = '</p>' . $this->spacer();
            $position = strpos($html, $searchStr);
            
            if ($position !== false) {
                $matriculaHtml = '<br/><b>' . $this->toolBox()->i18n()->trans('matricula') . ':</b> ' . $this->headerModel->matricula;
                $html = substr_replace($html, $matriculaHtml . $searchStr, $position, strlen($searchStr));
            }
        }

        return $html;
    }

    /**
     * Sobrescribe el método headerRight para añadir la matrícula.
     */
    protected function headerRight(): string
    {
        // Obtenemos el HTML original de la clase padre
        $html = parent::headerRight();

        // Añadimos la matrícula si existe
        if (property_exists($this->headerModel, 'matricula') && !empty($this->headerModel->matricula)) {
            $searchStr = '</p>' . $this->spacer();
            $position = strpos($html, $searchStr);
            
            if ($position !== false) {
                $matriculaHtml = '<br/><b>' . $this->toolBox()->i18n()->trans('matricula') . ':</b> ' . $this->headerModel->matricula;
                $html = substr_replace($html, $matriculaHtml . $searchStr, $position, strlen($searchStr));
            }
        }

        return $html;
    }
}