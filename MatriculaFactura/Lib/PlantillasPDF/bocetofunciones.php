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
    
        // Añadimos el campo 'matricula'
        $matricula = !empty($this->headerModel->matricula) ? '<br/><b>' . $this->toolBox()->i18n()->trans('matricula') . ':</b> ' . $this->headerModel->matricula : '';
    
        return '<table class="table-big">'
            . '<tr>'
            . '<td valign="top" width="35%">'
            . '<p><b>' . $this->getSubjectTitle($this->headerModel) . ': ' . $this->getSubjectName($this->headerModel) . '</b>'
            . '<br/>' . $this->getSubjectIdFiscalStr($this->headerModel)
            . '<br/>' . $this->combineAddress($this->headerModel)
            . $matricula  // Aquí se inserta el campo 'matricula'
            . '</p>'
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
    
        // Añadimos el campo 'matricula' debajo de los datos del cliente
        $matricula = !empty($this->headerModel->matricula) ? '<br/><b>' . $this->toolBox()->i18n()->trans('matricula') . ':</b> ' . $this->headerModel->matricula : '';
    
        return '<table class="table-big">'
            . '<tr>'
            . '<td valign="top"><img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/>' . '</td>'
            . '<td align="right" valign="top">' . $title
            . '<p><b>' . $this->getSubjectTitle($this->headerModel) . ': ' . $this->getSubjectName($this->headerModel) . '</b>'
            . '<br/>' . $this->getSubjectIdFiscalStr($this->headerModel)
            . '<br/>' . $this->combineAddress($this->headerModel)
            . $matricula // Aquí se inserta el campo 'matricula'
            . '</p>' . $this->spacer()
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
    
        // Añadimos el campo 'matricula'
        $matricula = !empty($this->headerModel->matricula) ? '<br/><b>' . $this->toolBox()->i18n()->trans('matricula') . ':</b> ' . $this->headerModel->matricula : '';
    
        return '<table class="table-big">'
            . '<tr>'
            . '<td>' . $title
            . '<p><b>' . $this->getSubjectTitle($this->headerModel) . ': ' . $this->getSubjectName($this->headerModel) . '</b>'
            . '<br/>' . $this->getSubjectIdFiscalStr($this->headerModel)
            . '<br/>' . $this->combineAddress($this->headerModel)
            . $matricula  // Aquí se inserta el campo 'matricula'
            . '</p>' . $this->spacer()
            . '<p>' . implode(' · ', $contactData) . '</p>'
            . '</td>'
            . '<td align="right"><img src="' . $this->logoPath . '" height="' . $this->get('logosize') . '"/></td>'
            . '</tr>'
            . '</table>';
    }