<?php
namespace FacturaScripts\Plugins\MatriculaFactura;

use FacturaScripts\Core\Base\AjaxForms\SalesHeaderHTML;



class Init extends \FacturaScripts\Core\Base\InitClass
{
    public function init()
    {
        // se ejecuta cada vez que carga FacturaScripts (si este plugin está activado).
        
        SalesHeaderHTML::addMod(new Mod\SalesHeaderHTMLMod()); 
        $this->loadExtension(new Extension\Controller\ListFacturaCliente());

        

       
    }

    public function update()
    {
        // se ejecuta cada vez que se instala o actualiza el plugin.
    }
}