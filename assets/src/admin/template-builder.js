const builder = document.getElementById('acg-template-builder');

if (builder) {
  const variablesInput = document.getElementById('acg_template_variables');
  let previewImage = document.getElementById('acg-template-image');
  let overlay = document.getElementById('acg-template-overlay');
  const tableBody = document.getElementById('acg-template-variables-body');
  const addButton = document.getElementById('acg-add-variable');
  const imageButton = document.getElementById('acg_template_select_image');
  const imageIdInput = document.getElementById('acg_template_image_id');
  const imagePreview = document.getElementById('acg_template_image_preview');

  const fontFamilies = [
    'Arial',
    'Helvetica',
    'Times New Roman',
    'Georgia',
    'Courier New',
    'Verdana',
  ];

  const dateFormats = [
    { value: 'Y-m-d', label: 'YYYY-MM-DD' },
    { value: 'm/d/Y', label: 'MM/DD/YYYY' },
    { value: 'd/m/Y', label: 'DD/MM/YYYY' },
    { value: 'F d, Y', label: 'Month DD, YYYY' },
    { value: 'd F Y', label: 'DD Month YYYY' },
  ];

  const autoTypes = [
    { value: 'certificate_id', label: 'Certificate ID' },
    { value: 'generation_date', label: 'Generation Date' },
  ];

  let variables = [];
  let saveTimeout = null;

  const parseVariables = (value) => {
    if (!value) {
      return [];
    }
    try {
      const parsed = JSON.parse(value);
      return Array.isArray(parsed) ? parsed : [];
    } catch (error) {
      return [];
    }
  };

  const ensureDefaults = (variable, index) => ({
    id: variable.id || `var_${Date.now()}_${index}`,
    label: variable.label || `Variable ${index + 1}`,
    key: variable.key || `variable_${index + 1}`,
    type: variable.type || 'text',
    required: variable.required ?? true,
    x: Number.isFinite(variable.x) ? variable.x : 0,
    y: Number.isFinite(variable.y) ? variable.y : 0,
    style: {
      font_family: variable.style?.font_family || 'Arial',
      font_size: variable.style?.font_size || 24,
      color: variable.style?.color || '#000000',
      align: variable.style?.align || 'left',
      bold: variable.style?.bold || false,
      italic: variable.style?.italic || false,
    },
    date_format: variable.date_format || 'Y-m-d',
    auto_type: variable.auto_type || 'certificate_id',
    image_max_width: variable.image_max_width || 300,
    image_max_height: variable.image_max_height || 300,
  });

  const updateHiddenInput = () => {
    if (!variablesInput) {
      return;
    }
    variablesInput.value = JSON.stringify(variables);
    scheduleRestSave();
  };

  const scheduleRestSave = () => {
    if (!window.acgAdmin || !window.acgAdmin.postId || !window.acgAdmin.restUrl) {
      return;
    }
    clearTimeout(saveTimeout);
    saveTimeout = setTimeout(() => {
      fetch(`${window.acgAdmin.restUrl}/templates/${window.acgAdmin.postId}/variables`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.acgAdmin.restNonce || '',
        },
        body: JSON.stringify({ variables }),
      }).catch(() => {});
    }, 800);
  };

  const getScale = () => {
    if (!previewImage) {
      return { scaleX: 1, scaleY: 1, width: 0, height: 0 };
    }
    const naturalWidth = previewImage.naturalWidth || 1;
    const naturalHeight = previewImage.naturalHeight || 1;
    return {
      scaleX: previewImage.clientWidth / naturalWidth,
      scaleY: previewImage.clientHeight / naturalHeight,
      width: previewImage.clientWidth,
      height: previewImage.clientHeight,
    };
  };

  const updateRowPositionInputs = (variable) => {
    const row = tableBody.querySelector(`tr[data-var-id="${variable.id}"]`);
    if (!row) {
      return;
    }
    const xInput = row.querySelector('input[data-field="x"]');
    const yInput = row.querySelector('input[data-field="y"]');
    if (xInput) {
      xInput.value = variable.x;
    }
    if (yInput) {
      yInput.value = variable.y;
    }
  };

  const updateOverlayPosition = (marker, variable) => {
    if (!previewImage) {
      return;
    }
    const { scaleX, scaleY } = getScale();
    marker.style.left = `${Math.round(variable.x * scaleX)}px`;
    marker.style.top = `${Math.round(variable.y * scaleY)}px`;
  };

  const enableDrag = (marker, variable) => {
    let dragging = false;
    let startX = 0;
    let startY = 0;
    let originLeft = 0;
    let originTop = 0;

    marker.addEventListener('pointerdown', (event) => {
      event.preventDefault();
      dragging = true;
      startX = event.clientX;
      startY = event.clientY;
      originLeft = marker.offsetLeft;
      originTop = marker.offsetTop;
      marker.setPointerCapture(event.pointerId);
    });

    marker.addEventListener('pointermove', (event) => {
      if (!dragging) {
        return;
      }
      const { width, height } = getScale();
      const deltaX = event.clientX - startX;
      const deltaY = event.clientY - startY;
      let nextLeft = originLeft + deltaX;
      let nextTop = originTop + deltaY;
      nextLeft = Math.max(0, Math.min(width, nextLeft));
      nextTop = Math.max(0, Math.min(height, nextTop));
      marker.style.left = `${Math.round(nextLeft)}px`;
      marker.style.top = `${Math.round(nextTop)}px`;
    });

    marker.addEventListener('pointerup', (event) => {
      if (!dragging) {
        return;
      }
      dragging = false;
      const { scaleX, scaleY } = getScale();
      const left = marker.offsetLeft;
      const top = marker.offsetTop;
      variable.x = Math.round(left / scaleX);
      variable.y = Math.round(top / scaleY);
      updateRowPositionInputs(variable);
      updateHiddenInput();
      marker.releasePointerCapture(event.pointerId);
    });
  };

  const buildSelect = (options, value) => {
    const select = document.createElement('select');
    options.forEach((option) => {
      const opt = document.createElement('option');
      opt.value = option.value;
      opt.textContent = option.label;
      if (option.value === value) {
        opt.selected = true;
      }
      select.appendChild(opt);
    });
    return select;
  };

  const renderRow = (variable) => {
    const row = document.createElement('tr');
    row.dataset.varId = variable.id;

    const labelCell = document.createElement('td');
    const labelInput = document.createElement('input');
    labelInput.type = 'text';
    labelInput.value = variable.label;
    labelInput.addEventListener('input', () => {
      variable.label = labelInput.value;
      updateHiddenInput();
      const marker = overlay?.querySelector(`[data-var-id="${variable.id}"]`);
      if (marker) {
        marker.textContent = variable.label || variable.key;
      }
    });
    labelCell.appendChild(labelInput);

    const keyCell = document.createElement('td');
    const keyInput = document.createElement('input');
    keyInput.type = 'text';
    keyInput.value = variable.key;
    keyInput.addEventListener('input', () => {
      variable.key = keyInput.value;
      updateHiddenInput();
    });
    keyCell.appendChild(keyInput);

    const typeCell = document.createElement('td');
    const typeSelect = buildSelect(
      [
        { value: 'text', label: 'Text' },
        { value: 'date', label: 'Date' },
        { value: 'image', label: 'Image' },
        { value: 'auto', label: 'Auto' },
      ],
      variable.type,
    );
    typeSelect.addEventListener('change', () => {
      variable.type = typeSelect.value;
      updateHiddenInput();
      renderVariables();
    });
    typeCell.appendChild(typeSelect);

    const requiredCell = document.createElement('td');
    const requiredInput = document.createElement('input');
    requiredInput.type = 'checkbox';
    requiredInput.checked = Boolean(variable.required);
    requiredInput.addEventListener('change', () => {
      variable.required = requiredInput.checked;
      updateHiddenInput();
    });
    requiredCell.appendChild(requiredInput);

    const styleCell = document.createElement('td');
    styleCell.className = 'acg-variable-style';

    const fontSelect = document.createElement('select');
    fontFamilies.forEach((font) => {
      const opt = document.createElement('option');
      opt.value = font;
      opt.textContent = font;
      opt.selected = font === variable.style.font_family;
      fontSelect.appendChild(opt);
    });
    fontSelect.addEventListener('change', () => {
      variable.style.font_family = fontSelect.value;
      updateHiddenInput();
      renderOverlay();
    });

    const sizeInput = document.createElement('input');
    sizeInput.type = 'number';
    sizeInput.min = '8';
    sizeInput.value = variable.style.font_size;
    sizeInput.addEventListener('input', () => {
      variable.style.font_size = Number(sizeInput.value) || 0;
      updateHiddenInput();
      renderOverlay();
    });

    const colorInput = document.createElement('input');
    colorInput.type = 'color';
    colorInput.value = variable.style.color;
    colorInput.addEventListener('input', () => {
      variable.style.color = colorInput.value;
      updateHiddenInput();
      renderOverlay();
    });

    const alignSelect = buildSelect(
      [
        { value: 'left', label: 'Left' },
        { value: 'center', label: 'Center' },
        { value: 'right', label: 'Right' },
      ],
      variable.style.align,
    );
    alignSelect.addEventListener('change', () => {
      variable.style.align = alignSelect.value;
      updateHiddenInput();
      renderOverlay();
    });

    const boldInput = document.createElement('input');
    boldInput.type = 'checkbox';
    boldInput.checked = Boolean(variable.style.bold);
    boldInput.addEventListener('change', () => {
      variable.style.bold = boldInput.checked;
      updateHiddenInput();
      renderOverlay();
    });

    const italicInput = document.createElement('input');
    italicInput.type = 'checkbox';
    italicInput.checked = Boolean(variable.style.italic);
    italicInput.addEventListener('change', () => {
      variable.style.italic = italicInput.checked;
      updateHiddenInput();
      renderOverlay();
    });

    const styleRow = document.createElement('div');
    styleRow.className = 'acg-style-row';
    styleRow.append('Font ', fontSelect, ' Size ', sizeInput, ' Color ', colorInput);

    const styleRow2 = document.createElement('div');
    styleRow2.className = 'acg-style-row';
    styleRow2.append('Align ', alignSelect, ' Bold ', boldInput, ' Italic ', italicInput);

    styleCell.appendChild(styleRow);
    styleCell.appendChild(styleRow2);

    const typeRow = document.createElement('div');
    typeRow.className = 'acg-style-row';

    if (variable.type === 'date') {
      const dateSelect = buildSelect(dateFormats, variable.date_format);
      dateSelect.addEventListener('change', () => {
        variable.date_format = dateSelect.value;
        updateHiddenInput();
      });
      typeRow.append('Format ', dateSelect);
    }

    if (variable.type === 'auto') {
      const autoSelect = buildSelect(autoTypes, variable.auto_type);
      autoSelect.addEventListener('change', () => {
        variable.auto_type = autoSelect.value;
        updateHiddenInput();
      });
      typeRow.append('Auto ', autoSelect);
    }

    if (variable.type === 'image') {
      const maxW = document.createElement('input');
      maxW.type = 'number';
      maxW.min = '1';
      maxW.value = variable.image_max_width;
      maxW.addEventListener('input', () => {
        variable.image_max_width = Number(maxW.value) || 0;
        updateHiddenInput();
      });

      const maxH = document.createElement('input');
      maxH.type = 'number';
      maxH.min = '1';
      maxH.value = variable.image_max_height;
      maxH.addEventListener('input', () => {
        variable.image_max_height = Number(maxH.value) || 0;
        updateHiddenInput();
      });

      typeRow.append('Max W ', maxW, ' Max H ', maxH);
    }

    if (typeRow.childNodes.length > 0) {
      styleCell.appendChild(typeRow);
    }

    const positionCell = document.createElement('td');
    const xInput = document.createElement('input');
    xInput.type = 'number';
    xInput.value = variable.x;
    xInput.dataset.field = 'x';
    xInput.addEventListener('input', () => {
      variable.x = Number(xInput.value) || 0;
      updateHiddenInput();
      renderOverlay();
    });

    const yInput = document.createElement('input');
    yInput.type = 'number';
    yInput.value = variable.y;
    yInput.dataset.field = 'y';
    yInput.addEventListener('input', () => {
      variable.y = Number(yInput.value) || 0;
      updateHiddenInput();
      renderOverlay();
    });

    positionCell.append('X ', xInput, ' Y ', yInput);

    const actionCell = document.createElement('td');
    const deleteButton = document.createElement('button');
    deleteButton.type = 'button';
    deleteButton.className = 'button';
    deleteButton.textContent = 'Remove';
    deleteButton.addEventListener('click', () => {
      variables = variables.filter((item) => item.id !== variable.id);
      updateHiddenInput();
      renderVariables();
    });
    actionCell.appendChild(deleteButton);

    row.append(labelCell, keyCell, typeCell, requiredCell, styleCell, positionCell, actionCell);
    tableBody.appendChild(row);
  };

  const applyMarkerStyles = (marker, variable) => {
    const { scaleX, scaleY } = getScale();
    const style = variable.style || {};
    marker.style.fontFamily = style.font_family || 'Arial';
    marker.style.fontSize = `${Math.round((style.font_size || 24) * Math.min(scaleX, scaleY))}px`;
    marker.style.color = style.color || '#000000';
    marker.style.fontWeight = style.bold ? 'bold' : 'normal';
    marker.style.fontStyle = style.italic ? 'italic' : 'normal';
    marker.style.textAlign = style.align || 'left';
  };

  const renderOverlay = () => {
    if (!overlay || !previewImage) {
      return;
    }
    overlay.innerHTML = '';
    variables.forEach((variable) => {
      const marker = document.createElement('div');
      marker.className = 'acg-variable-marker';
      marker.dataset.varId = variable.id;
      marker.textContent = variable.label || variable.key;
      updateOverlayPosition(marker, variable);
      applyMarkerStyles(marker, variable);
      overlay.appendChild(marker);
      enableDrag(marker, variable);
    });
  };

  const setPreviewImage = (url) => {
    const previewContainer = builder.querySelector('.acg-template-preview');
    if (!previewContainer) {
      return;
    }
    previewContainer.innerHTML = `
      <img id="acg-template-image" src="${url}" alt="" />
      <div id="acg-template-overlay" class="acg-template-overlay"></div>
    `;
    previewImage = document.getElementById('acg-template-image');
    overlay = document.getElementById('acg-template-overlay');
    if (previewImage) {
      if (!previewImage.complete) {
        previewImage.addEventListener('load', () => renderOverlay());
      } else {
        renderOverlay();
      }
    }
  };

  const renderVariables = () => {
    if (!tableBody) {
      return;
    }
    tableBody.innerHTML = '';
    variables.forEach((variable) => {
      renderRow(variable);
    });
    renderOverlay();
  };

  const addVariable = () => {
    const next = ensureDefaults({}, variables.length);
    variables.push(next);
    updateHiddenInput();
    renderVariables();
  };

  if (addButton) {
    addButton.addEventListener('click', () => addVariable());
  }

  if (imageButton && imageIdInput && imagePreview) {
    imageButton.addEventListener('click', () => {
      const frame = window.wp?.media({
        title: 'Select certificate template',
        button: { text: 'Use this image' },
        multiple: false,
      });

      if (!frame) {
        return;
      }

      frame.on('select', () => {
        const attachment = frame.state().get('selection').first().toJSON();
        imageIdInput.value = attachment.id;
        imagePreview.innerHTML = `<img src="${attachment.url}" style="max-width:100%;height:auto;" alt="" />`;
        setPreviewImage(attachment.url);
      });

      frame.open();
    });
  }

  variables = parseVariables(variablesInput?.value).map(ensureDefaults);
  renderVariables();

  if (previewImage) {
    if (!previewImage.complete) {
      previewImage.addEventListener('load', () => renderOverlay());
    }
    window.addEventListener('resize', () => renderOverlay());
  }
}
