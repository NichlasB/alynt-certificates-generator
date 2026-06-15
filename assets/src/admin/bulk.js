const bulkProgress = document.getElementById('acg-bulk-progress');

if (bulkProgress && window.acgAdmin?.restUrl) {
  const bulkId = bulkProgress.dataset.bulkId;
  const processedEl = bulkProgress.querySelector('[data-progress="processed"]');
  const failedEl = bulkProgress.querySelector('[data-progress="failed"]');
  const totalEl = bulkProgress.querySelector('[data-progress="total"]');
  const i18n = window.acgAdmin?.i18n || {};
  let intervalId = null;
  const pollInterval = 5000;

  const getSpinner = () => {
    let spinner = bulkProgress.querySelector('[data-progress="spinner"]');
    if (!spinner) {
      spinner = document.createElement('span');
      spinner.dataset.progress = 'spinner';
      spinner.className = 'spinner is-active';
      spinner.setAttribute('aria-hidden', 'true');
      bulkProgress.prepend(spinner);
    }
    return spinner;
  };

  const setProgressBusy = (isBusy) => {
    bulkProgress.setAttribute('aria-busy', isBusy ? 'true' : 'false');
    getSpinner().className = isBusy ? 'spinner is-active' : 'spinner';
  };

  const showMessage = (message, type = 'error') => {
    let messageEl = bulkProgress.querySelector('[data-progress="message"]');
    if (!messageEl) {
      messageEl = document.createElement('p');
      messageEl.dataset.progress = 'message';
      bulkProgress.appendChild(messageEl);
    }
    messageEl.className = `notice notice-${type}`;
    messageEl.textContent = message;
  };

  const updateProgress = (data) => {
    if (!data) {
      return;
    }
    if (processedEl) {
      processedEl.textContent = data.processed ?? 0;
    }
    if (failedEl) {
      failedEl.textContent = data.failed ?? 0;
    }
    if (totalEl) {
      totalEl.textContent = data.total ?? 0;
    }
    setProgressBusy(!data.complete);
    if (data.schedule_failed > 0) {
      showMessage(
        i18n.bulkScheduleFailed || `${data.schedule_failed} row(s) could not be scheduled.`,
        'warning'
      );
    }
    if (data.complete && intervalId) {
      clearInterval(intervalId);
      intervalId = null;
      setProgressBusy(false);
      showMessage(i18n.bulkComplete || 'Bulk generation complete.', 'success');
    }
  };

  const fetchStatus = () => {
    setProgressBusy(true);
    fetch(`${window.acgAdmin.restUrl}/bulk/${bulkId}/status`, {
      headers: {
        'X-WP-Nonce': window.acgAdmin.restNonce || '',
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error(i18n.bulkStatusFailed || 'Bulk status could not be loaded.');
        }
        return response.json();
      })
      .then((data) => updateProgress(data))
      .catch((error) => {
        setProgressBusy(false);
        showMessage(error.message || i18n.bulkStatusFailed || 'Bulk status could not be loaded.');
      });
  };

  const pollStatus = () => {
    if (document.hidden) {
      return;
    }
    fetchStatus();
  };

  fetchStatus();
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      fetchStatus();
    }
  });
  intervalId = setInterval(pollStatus, pollInterval);
}
