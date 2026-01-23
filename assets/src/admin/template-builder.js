const builder = document.getElementById('acg-template-builder');

if (builder) {
  const variablesInput = document.getElementById('acg_template_variables_input');
  let previewImage = document.getElementById('acg-template-image');
  let overlay = document.getElementById('acg-template-overlay');
  const tableBody = document.getElementById('acg-template-variables-body');
  const addButton = document.getElementById('acg-add-variable');
  const imageButton = document.getElementById('acg_template_select_image');
  const imageIdInput = document.getElementById('acg_template_image_id');
  const imagePreview = document.getElementById('acg_template_image_preview');

  // Get authoritative image dimensions from data attributes (set by PHP from actual file)
  let authoritativeWidth = parseInt(builder.dataset.imageWidth, 10) || 0;
  let authoritativeHeight = parseInt(builder.dataset.imageHeight, 10) || 0;

  // System fonts (built-in, always available)
  const systemFonts = [
    { family: 'Arial', slug: 'arial' },
    { family: 'Helvetica', slug: 'helvetica' },
    { family: 'Times New Roman', slug: 'times_new_roman' },
    { family: 'Georgia', slug: 'georgia' },
    { family: 'Courier New', slug: 'courier_new' },
    { family: 'Verdana', slug: 'verdana' },
  ];

  // Custom fonts will be loaded from API
  let customFonts = {
    global: [],
    template: [],
  };

  // Combined font list for dropdowns
  let allFonts = [...systemFonts];

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
  let initialized = false; // Prevent REST saves during initial load
  let migratedOnLoad = false;

  /**
   * Fetch available fonts from REST API.
   * Loads system fonts, global custom fonts, and template-specific fonts.
   */
  const fetchAvailableFonts = async () => {
    if (!window.acgAdmin || !window.acgAdmin.restUrl) {
      return;
    }

    const templateId = window.acgAdmin.postId || 0;
    const url = `${window.acgAdmin.restUrl}/fonts${templateId ? `?template_id=${templateId}` : ''}`;

    try {
      const response = await fetch(url, {
        method: 'GET',
        headers: {
          'X-WP-Nonce': window.acgAdmin.restNonce || '',
        },
      });

      if (!response.ok) {
        return;
      }

      const data = await response.json();

      // Store custom fonts
      customFonts.global = data.global || [];
      customFonts.template = data.template || [];

      // Rebuild allFonts list: system + global + template
      allFonts = [
        ...systemFonts.map((f) => ({ ...f, type: 'system' })),
        ...customFonts.global.map((f) => ({ ...f, type: 'global' })),
        ...customFonts.template.map((f) => ({ ...f, type: 'template' })),
      ];

      // Re-render if variables are already loaded
      if (initialized && variables.length > 0) {
        renderVariables();
      }
    } catch {
      // Silently fail - system fonts will still be available
    }
  };

  // Fetch fonts on load
  fetchAvailableFonts();

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

  /**
   * Check if a coordinate value is in legacy pixel format (> 1) or new percentage format (0-1).
   * @param {number} value - The coordinate value
   * @returns {boolean} - True if legacy pixel format
   */
  const isLegacyPixelCoordinate = (value) => {
    return value > 1;
  };

  const getAlign = (variable) => variable?.style?.align || 'left';

  const clamp01 = (value) => Math.max(0, Math.min(1, value));

  const getAnchorOffsetPx = (markerWidth, align) => {
    if (align === 'center') {
      return markerWidth / 2;
    }
    if (align === 'right') {
      return markerWidth;
    }
    return 0;
  };

  /**
   * Migrate legacy pixel coordinates to percentage format.
   * Uses authoritative dimensions if available, otherwise estimates from the value.
   * @param {object} variable - Variable object with x, y coordinates
   * @returns {object} - Variable with migrated coordinates
   */
  const migrateCoordinates = (variable) => {
    const x = Number.isFinite(variable.x) ? variable.x : 0;
    const y = Number.isFinite(variable.y) ? variable.y : 0;

    // New: store coordinates as percentages, plus a coord_mode flag so we can migrate safely once.
    // - coord_mode=percent_left: x/y represent top-left of the marker (legacy behavior)
    // - coord_mode=percent_anchor: x/y represent an anchor point (new behavior), used with align adjustments in PDF.
    const coordMode = variable.coord_mode || '';

    // If already migrated to anchor mode, keep as-is.
    if (coordMode === 'percent_anchor') {
      return { ...variable, x, y, coord_mode: 'percent_anchor' };
    }

    // If legacy pixels, first convert to percent_left.
    if (isLegacyPixelCoordinate(x) || isLegacyPixelCoordinate(y)) {
      migratedOnLoad = true;

      let newX = x;
      let newY = y;

      if (authoritativeWidth > 0 && isLegacyPixelCoordinate(x)) {
        newX = x / authoritativeWidth;
      }
      if (authoritativeHeight > 0 && isLegacyPixelCoordinate(y)) {
        newY = y / authoritativeHeight;
      }

      return { ...variable, x: clamp01(newX), y: clamp01(newY), coord_mode: 'percent_left' };
    }

    // Already percent values, but not yet marked — assume legacy percent_left.
    return { ...variable, x: clamp01(x), y: clamp01(y), coord_mode: coordMode || 'percent_left' };
  };

  const ensureDefaults = (variable, index) => {
    // First apply defaults
    const withDefaults = {
      id: variable.id || `var_${Date.now()}_${index}`,
      label: variable.label || `Variable ${index + 1}`,
      key: variable.key || `variable_${index + 1}`,
      type: variable.type || 'text',
      required: variable.required ?? true,
      display_on_certificate: variable.display_on_certificate ?? true,
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
      coord_mode: variable.coord_mode || '',
    };

    // Then migrate coordinates if needed
    return migrateCoordinates(withDefaults);
  };

  const updateHiddenInput = () => {
    if (!variablesInput) {
      return;
    }
    variablesInput.value = JSON.stringify(variables);
    scheduleRestSave();
  };

  const scheduleRestSave = () => {
    // Don't trigger REST saves during initial page load or if not fully initialized
    if (!initialized) {
      return;
    }
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

  /**
   * Get display dimensions and scale factors.
   * Uses authoritative dimensions from data attributes for consistent scaling.
   */
  const getScale = () => {
    if (!previewImage) {
      return { scaleX: 1, scaleY: 1, width: 0, height: 0, imgWidth: authoritativeWidth, imgHeight: authoritativeHeight };
    }

    const displayWidth = previewImage.clientWidth || 1;
    const displayHeight = previewImage.clientHeight || 1;

    // Use authoritative dimensions from PHP, fallback to naturalWidth/Height
    const imgWidth = authoritativeWidth || previewImage.naturalWidth || 1;
    const imgHeight = authoritativeHeight || previewImage.naturalHeight || 1;

    return {
      scaleX: displayWidth / imgWidth,
      scaleY: displayHeight / imgHeight,
      width: displayWidth,
      height: displayHeight,
      imgWidth,
      imgHeight,
    };
  };

  /**
   * Format percentage for display (e.g., 0.456 -> "45.6%")
   */
  const formatPercentage = (value) => {
    return `${(value * 100).toFixed(1)}%`;
  };

  /**
   * Parse percentage input (e.g., "45.6%" or "45.6" or "0.456" -> 0.456)
   */
  const parsePercentageInput = (input) => {
    const str = String(input).trim();
    // If it ends with %, parse as percentage
    if (str.endsWith('%')) {
      return parseFloat(str) / 100;
    }
    // If it's a small number (0-1 range), treat as decimal
    const num = parseFloat(str);
    if (num >= 0 && num <= 1) {
      return num;
    }
    // If it's a larger number, assume it's a percentage without the % sign
    if (num > 1 && num <= 100) {
      return num / 100;
    }
    return 0;
  };

  const updateRowPositionInputs = (variable) => {
    const row = tableBody.querySelector(`tr[data-var-id="${variable.id}"]`);
    if (!row) {
      return;
    }
    const xInput = row.querySelector('input[data-field="x"]');
    const yInput = row.querySelector('input[data-field="y"]');
    if (xInput) {
      xInput.value = formatPercentage(variable.x);
    }
    if (yInput) {
      yInput.value = formatPercentage(variable.y);
    }
  };

  /**
   * Update marker position on overlay using percentage coordinates.
   * Converts percentage (0-1) to display pixels.
   */
  const updateOverlayPosition = (marker, variable) => {
    if (!previewImage) {
      return;
    }
    const { width, height } = getScale();
    const align = getAlign(variable);
    const markerWidth = marker.offsetWidth || 0;

    // Convert percentage to display pixels.
    // If in anchor mode, x represents an anchor point (center/right/left), so we shift by marker width.
    const anchorX = variable.x * width;
    const left = variable.coord_mode === 'percent_anchor'
      ? anchorX - getAnchorOffsetPx(markerWidth, align)
      : variable.x * width;

    marker.style.left = `${Math.round(left)}px`;
    marker.style.top = `${Math.round(variable.y * height)}px`;
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
      const align = getAlign(variable);
      const markerWidth = marker.offsetWidth || 0;
      const anchorOffset = getAnchorOffsetPx(markerWidth, align);
      const deltaX = event.clientX - startX;
      const deltaY = event.clientY - startY;
      let nextLeft = originLeft + deltaX;
      let nextTop = originTop + deltaY;
      // Clamp based on anchor, so center/right aligned markers can reach the edges cleanly.
      const nextAnchorX = Math.max(0, Math.min(width, nextLeft + anchorOffset));
      nextLeft = nextAnchorX - anchorOffset;
      nextTop = Math.max(0, Math.min(height, nextTop));
      marker.style.left = `${Math.round(nextLeft)}px`;
      marker.style.top = `${Math.round(nextTop)}px`;
    });

    marker.addEventListener('pointerup', (event) => {
      if (!dragging) {
        return;
      }
      dragging = false;
      const { width, height } = getScale();
      const align = getAlign(variable);
      const markerWidth = marker.offsetWidth || 0;
      const anchorOffset = getAnchorOffsetPx(markerWidth, align);
      const left = marker.offsetLeft;
      const top = marker.offsetTop;

      // Convert display pixels to percentage (0-1 range)
      // Store x as an anchor coordinate so PDF alignment works predictably.
      const anchorX = left + anchorOffset;
      variable.x = width > 0 ? anchorX / width : 0;
      variable.y = height > 0 ? top / height : 0;

      // Clamp to valid range
      variable.x = clamp01(variable.x);
      variable.y = clamp01(variable.y);
      variable.coord_mode = 'percent_anchor';

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

    const displayCell = document.createElement('td');
    const displayInput = document.createElement('input');
    displayInput.type = 'checkbox';
    displayInput.checked = Boolean(variable.display_on_certificate);
    displayInput.addEventListener('change', () => {
      variable.display_on_certificate = displayInput.checked;
      updateHiddenInput();
      renderOverlay();
    });
    displayCell.appendChild(displayInput);

    const styleCell = document.createElement('td');
    styleCell.className = 'acg-variable-style';

    const fontSelect = document.createElement('select');

    // Helper to add fonts to an optgroup
    const addFontsToGroup = (fonts, groupLabel) => {
      if (fonts.length === 0) {
        return;
      }
      const optgroup = document.createElement('optgroup');
      optgroup.label = groupLabel;
      fonts.forEach((font) => {
        const opt = document.createElement('option');
        opt.value = font.family;
        opt.textContent = font.family;
        // Check if selected (match by family name)
        opt.selected = font.family === variable.style.font_family;
        optgroup.appendChild(opt);
      });
      fontSelect.appendChild(optgroup);
    };

    // Group fonts by type
    const systemFontList = allFonts.filter((f) => f.type === 'system' || !f.type);
    const globalFontList = allFonts.filter((f) => f.type === 'global');
    const templateFontList = allFonts.filter((f) => f.type === 'template');

    // Add groups (only show groups that have fonts)
    addFontsToGroup(systemFontList, 'System Fonts');
    addFontsToGroup(globalFontList, 'Custom Fonts (Global)');
    addFontsToGroup(templateFontList, 'Custom Fonts (Template)');

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

    // Container for position inputs
    const positionContainer = document.createElement('div');
    positionContainer.className = 'acg-position-inputs';

    // X input row
    const xRow = document.createElement('div');
    xRow.className = 'acg-position-row';
    const xLabel = document.createElement('label');
    xLabel.textContent = 'X:';
    xLabel.style.marginRight = '4px';
    
    // X input - displays and accepts percentage values
    const xInput = document.createElement('input');
    xInput.type = 'text';
    xInput.value = formatPercentage(variable.x);
    xInput.dataset.field = 'x';
    xInput.style.width = '70px';
    xInput.addEventListener('change', () => {
      variable.x = parsePercentageInput(xInput.value);
      variable.x = Math.max(0, Math.min(1, variable.x));
      xInput.value = formatPercentage(variable.x);
      updateHiddenInput();
      renderOverlay();
    });

    xRow.appendChild(xLabel);
    xRow.appendChild(xInput);

    // Y input row
    const yRow = document.createElement('div');
    yRow.className = 'acg-position-row';
    const yLabel = document.createElement('label');
    yLabel.textContent = 'Y:';
    yLabel.style.marginRight = '4px';
    
    // Y input - displays and accepts percentage values
    const yInput = document.createElement('input');
    yInput.type = 'text';
    yInput.value = formatPercentage(variable.y);
    yInput.dataset.field = 'y';
    yInput.style.width = '70px';
    yInput.addEventListener('change', () => {
      variable.y = parsePercentageInput(yInput.value);
      variable.y = Math.max(0, Math.min(1, variable.y));
      yInput.value = formatPercentage(variable.y);
      updateHiddenInput();
      renderOverlay();
    });

    yRow.appendChild(yLabel);
    yRow.appendChild(yInput);

    positionContainer.appendChild(xRow);
    positionContainer.appendChild(yRow);
    positionCell.appendChild(positionContainer);

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

    row.append(labelCell, keyCell, typeCell, requiredCell, displayCell, styleCell, positionCell, actionCell);
    tableBody.appendChild(row);
  };

  const applyMarkerStyles = (marker, variable) => {
    const { width, height, imgWidth, imgHeight } = getScale();
    const style = variable.style || {};

    // Calculate scale based on display vs actual image dimensions
    const displayScale = imgWidth > 0 && imgHeight > 0
      ? Math.min(width / imgWidth, height / imgHeight)
      : 1;

    marker.style.fontFamily = style.font_family || 'Arial';
    marker.style.fontSize = `${Math.round((style.font_size || 24) * displayScale)}px`;
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

      applyMarkerStyles(marker, variable);

      // Visual indicator for hidden variables (won't appear on PDF).
      if (variable.display_on_certificate === false) {
        marker.style.opacity = '0.4';
        marker.style.textDecoration = 'line-through';
      }

      overlay.appendChild(marker);

      // One-time migration for old saved values:
      // Previously, x was effectively treated as the marker's LEFT edge in the UI, even when align=center/right.
      // But PDF treats x as the anchor point and shifts left based on text width, causing left drift.
      // Here, if we detect legacy percent_left and center/right align, convert x to anchor based on marker width.
      if (variable.coord_mode === 'percent_left') {
        const align = getAlign(variable);
        if (align === 'center' || align === 'right') {
          const { imgWidth } = getScale();
          const markerWidth = marker.offsetWidth || 0;
          const anchorOffsetImgPx = imgWidth > 0 ? (getAnchorOffsetPx(markerWidth, align) / (previewImage.clientWidth || 1)) * imgWidth : 0;
          const xImgPx = variable.x * imgWidth;
          const migrated = (xImgPx + anchorOffsetImgPx) / imgWidth;
          variable.x = clamp01(migrated);
          variable.coord_mode = 'percent_anchor';
          migratedOnLoad = true;
          updateRowPositionInputs(variable);
        }
      }

      updateOverlayPosition(marker, variable);
      enableDrag(marker, variable);
    });
  };

  const setPreviewImage = (url, width, height) => {
    const previewContainer = builder.querySelector('.acg-template-preview');
    if (!previewContainer) {
      return;
    }

    // Update authoritative dimensions if provided
    if (width && height) {
      authoritativeWidth = width;
      authoritativeHeight = height;
      builder.dataset.imageWidth = width;
      builder.dataset.imageHeight = height;
    }

    previewContainer.innerHTML = `
      <img id="acg-template-image" src="${url}" alt="" />
      <div id="acg-template-overlay" class="acg-template-overlay"></div>
    `;
    previewImage = document.getElementById('acg-template-image');
    overlay = document.getElementById('acg-template-overlay');
    if (previewImage) {
      if (!previewImage.complete) {
        previewImage.addEventListener('load', () => {
          // Update authoritative dimensions from loaded image if not set
          if (!authoritativeWidth || !authoritativeHeight) {
            authoritativeWidth = previewImage.naturalWidth;
            authoritativeHeight = previewImage.naturalHeight;
            builder.dataset.imageWidth = authoritativeWidth;
            builder.dataset.imageHeight = authoritativeHeight;
          }
          renderOverlay();
        });
      } else {
        // Update authoritative dimensions from loaded image if not set
        if (!authoritativeWidth || !authoritativeHeight) {
          authoritativeWidth = previewImage.naturalWidth;
          authoritativeHeight = previewImage.naturalHeight;
          builder.dataset.imageWidth = authoritativeWidth;
          builder.dataset.imageHeight = authoritativeHeight;
        }
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
        // Pass dimensions from attachment metadata
        setPreviewImage(attachment.url, attachment.width, attachment.height);
      });

      frame.open();
    });
  }

  // Parse and migrate variables on load
  variables = parseVariables(variablesInput?.value).map(ensureDefaults);
  renderVariables();

  // Mark as initialized after initial render - REST saves now allowed on user interaction
  setTimeout(() => {
    initialized = true;
    // If we migrated legacy pixel coordinates on load, persist the migrated values.
    // Without this, the preview will look correct but the saved JSON (used for PDF generation)
    // will still contain old pixel coordinates until the user edits something.
    if (migratedOnLoad) {
      updateHiddenInput();
      migratedOnLoad = false;
    }
  }, 100);

  if (previewImage) {
    if (!previewImage.complete) {
      previewImage.addEventListener('load', () => {
        // Update authoritative dimensions from loaded image if not set
        if (!authoritativeWidth || !authoritativeHeight) {
          authoritativeWidth = previewImage.naturalWidth;
          authoritativeHeight = previewImage.naturalHeight;
          builder.dataset.imageWidth = authoritativeWidth;
          builder.dataset.imageHeight = authoritativeHeight;
        }
        renderOverlay();
      });
    }
    window.addEventListener('resize', () => renderOverlay());
  }

  // Cancel any pending REST saves when the form is submitted to prevent race conditions.
  const form = document.getElementById('post');
  if (form) {
    form.addEventListener('submit', () => {
      clearTimeout(saveTimeout);
      saveTimeout = null;
    });
  }
}
