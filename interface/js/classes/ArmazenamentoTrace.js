export class ArmazenamentoTrace {
  constructor() {
    this.state = {
      page: "manifests",
      loaded: 0,
      planned: 0,
      running: false,
      emergency: false,
      returnMode: false,
      loadingId: null,
      selectedLoadingId: null,
      activeLoadings: [],
      operationalState: "AGUARDANDO",
      truck: "—",
      romaneio: "—",
      equipmentCode: "—",
      equipmentId: null,
      monitoring: null,
      syncStatus: null,
      report: null,
      reportCsvRows: [],
      products: [],
      productsLoaded: false,
      users: [],
      userRole: null,
      configuration: null,
      equipments: [],
      equipmentsLoaded: false,
      equipmentsLoading: false,
      equipmentsError: "",
      dalaStatuses: [],
      companies: [],
      companyDetail: null,
      selectedCompanyId: null,
      manifestFilters: {
        date_from: "",
        date_to: "",
        number: "",
        expedidor: "",
        status: "",
      },
      manifestPage: 1,
      manifestMeta: { page: 1, per_page: 50, total: 0, pages: 0 },
      reportFilters: { date_from: "", date_to: "", status: "" },
      dashboard: null,
      manifestDetail: null,
      equipmentDetail: null,
      dalaCommands: [],
      dalaActionConfig: { acoes: [], gatilhos: [], can_manage: false },
      errorLogs: [],
      plcCommand: null,
      productFormOpen: false,
      dalaFormOpen: false,
      editingProductId: null,
      productSearch: "",
      loadErrors: [],
      pendingReadings: [],
    };
    this.csrfToken = "";
    this.manifests = [];
  }
  navigate(page) {
    this.state.page = page;
  }
  setUser(user) {
    this.state.userRole = user?.role || null;
    this.state.currentUserId = user?.id || null;
  }
  setCsrfToken(token) {
    this.csrfToken = token || "";
  }
  jsonHeaders() {
    return {
      "Content-Type": "application/json",
      ...(this.csrfToken ? { "X-CSRF-Token": this.csrfToken } : {}),
    };
  }
  async loadManifests() {
    const params = new URLSearchParams();
    Object.entries(this.state.manifestFilters || {}).forEach(([key, value]) => {
      if (value) params.set(key, value);
    });
    const page = Math.max(1, Number(this.state.manifestPage) || 1);
    params.set("page", String(page));
    params.set("per_page", "50");
    const query = params.toString();
    const response = await fetch(
      `/api/romaneios.php${query ? `?${query}` : ""}`,
    );
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
      const message =
        response.status === 401
          ? "Sua sessão expirou. Entre novamente no sistema."
          : [429, 503].includes(response.status)
            ? "O servidor está ocupado. Aguarde alguns segundos e tente novamente."
            : result.error || "Não foi possível carregar os romaneios.";
      throw new Error(message);
    }
    this.manifests = result.data || [];
    this.state.manifestMeta = result.meta || {
      page,
      per_page: 50,
      total: this.manifests.length,
      pages: this.manifests.length ? 1 : 0,
    };
  }
  async applyManifestFilters(raw) {
    this.state.manifestPage = 1;
    this.state.manifestFilters = {
      date_from: String(raw.date_from || "").trim(),
      date_to: String(raw.date_to || "").trim(),
      number: String(raw.number || "").trim(),
      expedidor: String(raw.expedidor || "").trim(),
      status: String(raw.status || "").trim(),
    };
    await this.loadManifests();
  }
  async clearManifestFilters() {
    this.state.manifestPage = 1;
    this.state.manifestFilters = {
      date_from: "",
      date_to: "",
      number: "",
      expedidor: "",
      status: "",
    };
    await this.loadManifests();
  }
  async setManifestPage(page) {
    const pages = Math.max(1, Number(this.state.manifestMeta?.pages) || 1);
    this.state.manifestPage = Math.min(pages, Math.max(1, Number(page) || 1));
    await this.loadManifests();
  }
  async loadDashboard() {
    const response = await fetch("/api/dashboard.php");
    if (response.ok) this.state.dashboard = (await response.json()).data;
  }
  async loadReport() {
    const params = new URLSearchParams();
    Object.entries(this.state.reportFilters || {}).forEach(([key, value]) => {
      if (value) params.set(key, value);
    });
    const response = await fetch(
      `/api/relatorio_operacional.php?${params.toString()}`,
    );
    const result = await response.json().catch(() => ({}));
    if (!response.ok)
      throw new Error(result.error || "Não foi possível carregar o relatório.");
    this.state.report = result.data;
  }
  async applyReportFilters(raw) {
    this.state.reportFilters = {
      date_from: String(raw.date_from || "").trim(),
      date_to: String(raw.date_to || "").trim(),
      status: String(raw.status || "").trim(),
    };
    await this.loadReport();
  }
  async loadManifest(id) {
    const response = await fetch(`/api/romaneios.php?id=${id}`);
    const result = await response.json();
    if (!response.ok)
      throw new Error(result.error || "Romaneio não encontrado.");
    this.state.manifestDetail = result.data;
  }
  async createManifest(payload) {
    const response = await fetch("/api/romaneios.php", {
      method: "POST",
      headers: this.jsonHeaders(),
      body: JSON.stringify(payload),
    });
    const result = await response.json();
    if (!response.ok)
      throw new Error(result.error || "Não foi possível salvar o romaneio.");
    await this.loadManifests();
    return result.data;
  }
  async updateManifest(payload) {
    const response = await fetch("/api/romaneios.php", {
      method: "PATCH",
      headers: this.jsonHeaders(),
      body: JSON.stringify({ action: "update", ...payload }),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok)
      throw new Error(result.error || "Não foi possível atualizar o romaneio.");
    await this.loadManifests();
    return result.data;
  }
  async prepareLoading(payload) {
    const response = await fetch("/api/carregamentos.php", {
      method: "POST",
      headers: this.jsonHeaders(),
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok)
      throw new Error(
        result.error || "Não foi possível preparar o carregamento.",
      );
    this.state.selectedLoadingId = Number(result.data.id);
    await this.loadActiveLoading(this.state.selectedLoadingId);
    return result.data;
  }
  async importPdf(formData) {
    const response = await fetch("/api/importar_pdf.php", {
      method: "POST",
      headers: this.csrfToken ? { "X-CSRF-Token": this.csrfToken } : {},
      body: formData,
    });
    const result = await response.json();
    if (!response.ok)
      throw new Error(result.error || "Falha ao importar o PDF.");
    return result.data;
  }
  async loadEquipment(id) {
    const response = await fetch(`/api/equipamentos.php?id=${id}`);
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || "Dala não encontrada.");
    this.state.equipmentDetail = result.data;
  }
  async checkEquipmentStatus(id) {
    const response = await fetch(`/api/equipamentos.php?id=${id}&check=status`);
    const result = await response.json();
    if (!response.ok)
      return {
        status: "DESCONHECIDO",
        message: result.error || "Falha na verificação.",
      };
    return result.data;
  }
  async loadDalaStatuses() {
    const response = await fetch("/api/status_dalas.php");
    const result = await response.json().catch(() => ({}));
    if (!response.ok)
      throw new Error(result.error || "Não foi possível carregar o status das Dalas.");
    this.state.dalaStatuses = Array.isArray(result.data) ? result.data : [];
    return this.state.dalaStatuses;
  }
  async loadDalaCommandHistory(id) {
    const response = await fetch(`/api/comandos_industriais.php?equipment_id=${encodeURIComponent(id)}`);
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || "Não foi possível carregar o diagnóstico do CLP.");
    this.state.dalaCommands = Array.isArray(result.data) ? result.data : [];
    return this.state.dalaCommands;
  }
  async loadDalaActionConfig(id) {
    const response = await fetch(`/api/acoes_dala.php?equipment_id=${encodeURIComponent(id)}`);
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || "Não foi possível carregar as ações da Dala.");
    this.state.dalaActionConfig = result.data || { acoes: [], gatilhos: [], can_manage: false };
    return this.state.dalaActionConfig;
  }
  async saveDalaAction(id, payload) {
    const method = payload.id ? "PATCH" : "POST";
    const response = await fetch(`/api/acoes_dala.php?equipment_id=${encodeURIComponent(id)}`, {
      method,
      headers: this.jsonHeaders(),
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || "Não foi possível salvar a ação.");
    return this.loadDalaActionConfig(id);
  }
  async reorderDalaActions(id, ids) {
    const response = await fetch(`/api/acoes_dala.php?equipment_id=${encodeURIComponent(id)}`, {
      method: "PATCH", headers: this.jsonHeaders(), body: JSON.stringify({ action: "reorder", ids }),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || "Não foi possível ordenar as ações.");
    return this.loadDalaActionConfig(id);
  }
  async deleteDalaAction(equipmentId, actionId) {
    const response = await fetch(`/api/acoes_dala.php?equipment_id=${encodeURIComponent(equipmentId)}&id=${encodeURIComponent(actionId)}`, {
      method: "DELETE", headers: this.jsonHeaders(),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || "Não foi possível excluir a ação.");
    return this.loadDalaActionConfig(equipmentId);
  }
  async saveDalaTrigger(equipmentId, payload) {
    const response = await fetch(`/api/acoes_dala.php?equipment_id=${encodeURIComponent(equipmentId)}`, {
      method: "PATCH", headers: this.jsonHeaders(), body: JSON.stringify({ action: "trigger", ...payload }),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || "Não foi possível salvar o gatilho.");
    return this.loadDalaActionConfig(equipmentId);
  }
  async loadErrorLogs() {
    const response = await fetch("/api/logs_erros.php");
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || "Não foi possível carregar os logs de erro.");
    this.state.errorLogs = result.data || [];
    return this.state.errorLogs;
  }
  async updateEquipment(data) {
    const response = await fetch("/api/equipamentos.php", {
      method: "PUT",
      headers: this.jsonHeaders(),
      body: JSON.stringify(data),
    });
    const result = await response.json();
    if (!response.ok)
      throw new Error(result.error || "Não foi possível salvar a Dala.");
    await this.loadEquipments();
  }
  async deleteEquipment(id) {
    const response = await fetch(`/api/equipamentos.php?id=${id}`, {
      method: "DELETE",
      headers: this.jsonHeaders(),
    });
    const result = await response.json();
    if (!response.ok)
      throw new Error(result.error || "Não foi possível excluir a Dala.");
    await this.loadEquipments();
  }
  updateProduct(data) {
    return this.requestJson("/api/produtos.php", "PUT", data);
  }
  deleteProduct(id) {
    return this.requestJson(`/api/produtos.php?id=${id}`, "DELETE", {});
  }
  async requestJson(url, method, body) {
    const response = await fetch(url, {
      method,
      headers: this.jsonHeaders(),
      body: method === "DELETE" ? undefined : JSON.stringify(body),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || "Falha na requisição.");
    await this.loadProducts();
    return result.data;
  }
  async loadActiveLoading(selectedId = this.state.selectedLoadingId) {
    const response = await fetch("/api/carregamentos.php");
    const result = await response.json().catch(() => ({}));
    if (!response.ok)
      throw new Error(
        result.error || "Não foi possível carregar o carregamento ativo.",
      );
    const loadings = Array.isArray(result.data) ? result.data : [];
    this.state.activeLoadings = loadings.filter(
      (item) => item.state !== "FINALIZADO",
    );
    const requestedId = Number(selectedId) || null;
    const loading =
      this.state.activeLoadings.find(
        (item) => Number(item.id) === requestedId,
      ) ||
      (this.state.activeLoadings.length === 1
        ? this.state.activeLoadings[0]
        : null);
    if (!loading) {
      this.state.loadingId = null;
      this.state.selectedLoadingId = null;
      this.state.returnMode = false;
      this.state.loaded = 0;
      this.state.planned = 0;
      this.state.running = false;
      this.state.emergency = false;
      this.state.operationalState = "AGUARDANDO";
      this.state.truck = "—";
      this.state.romaneio = "—";
      this.state.equipmentCode = "—";
      this.state.equipmentId = null;
      this.state.plcCommand = null;
      return;
    }
    this.state.loadingId = Number(loading.id);
    this.state.selectedLoadingId = Number(loading.id);
    this.state.operationalState = loading.state;
    this.state.emergency = loading.state === "EMERGENCIA";
    this.state.running = ["CARREGANDO", "FINALIZANDO"].includes(loading.state);
    this.state.planned = Number(loading.planned_quantity) || 0;
    this.state.loaded = Number(loading.valid_readings) || 0;
    this.state.truck = loading.plate || "—";
    this.state.romaneio = loading.romaneio_number || "—";
    this.state.equipmentCode = loading.equipment_code || "—";
    this.state.equipmentId = Number(loading.equipment_id) || null;
    await this.loadPlcCommandStatus(this.state.loadingId);
  }
  clpDaDalaAtual() {
    const devices = this.state.monitoring?.dispositivos || [];
    return devices.find(
      (device) =>
        device.device_type === "CLP" &&
        Number(device.equipment_id) === Number(this.state.equipmentId),
    );
  }
  clpDisponivel() {
    return this.clpDaDalaAtual()?.status === "ONLINE";
  }
  mensagemClpIndisponivel() {
    const device = this.clpDaDalaAtual();
    const seconds = Number(device?.segundos_sem_sinal);
    const time = Number.isFinite(seconds) ? ` há ${seconds} segundos` : "";
    return `CLP sem comunicação${time}. Novos comandos estão bloqueados; reconectando a cada 2 segundos.`;
  }
  async changeLoadingState(target, loadingId = this.state.loadingId) {
    if (!loadingId) throw new Error("Nenhum carregamento ativo encontrado.");
    const response = await fetch("/api/estado_carregamento.php", {
      method: "PATCH",
      headers: this.jsonHeaders(),
      body: JSON.stringify({ carregamento_id: loadingId, state: target }),
    });
    const result = await response.json();
    if (!response.ok)
      throw new Error(result.error || "Não foi possível alterar o estado.");
    if (Number(loadingId) === Number(this.state.loadingId)) {
      this.state.operationalState = result.data.state;
      this.state.running = ["CARREGANDO", "FINALIZANDO"].includes(
        result.data.state,
      );
      this.state.emergency = result.data.state === "EMERGENCIA";
    }
    return result.data;
  }
  async requestMachineReverse(loadingId, command = "REVERSAO_ATIVAR") {
    if (!loadingId)
      throw new Error("Nenhum carregamento ativo para esta Dala.");
    const response = await fetch("/api/comando_maquina.php", {
      method: "POST",
      headers: this.jsonHeaders(),
      body: JSON.stringify({ carregamento_id: loadingId, command }),
    });
    const result = await response.json();
    if (!response.ok)
      throw new Error(result.error || "Não foi possível solicitar a reversão.");
    this.state.plcCommand = {
      id: result.data.command_request_id,
      command: result.data.command,
      status: "PENDENTE",
      response_message: null,
    };
    return result.data;
  }
  async loadPlcCommandStatus(loadingId = this.state.loadingId) {
    if (!loadingId) {
      this.state.plcCommand = null;
      return null;
    }
    const response = await fetch(
      `/api/comandos_industriais.php?carregamento_id=${encodeURIComponent(loadingId)}`,
    );
    const result = await response.json().catch(() => ({}));
    if (!response.ok)
      throw new Error(
        result.error || "Não foi possível consultar o comando industrial.",
      );
    this.state.plcCommand = result.data || null;
    return this.state.plcCommand;
  }
  async unlockMachine() {
    if (!this.state.loadingId)
      throw new Error("Nenhum carregamento ativo encontrado.");
    const response = await fetch("/api/desbloquear_maquina.php", {
      method: "POST",
      headers: this.jsonHeaders(),
      body: JSON.stringify({ carregamento_id: this.state.loadingId }),
    });
    const result = await response.json();
    if (!response.ok) {
      if (response.status === 409) {
        await this.loadActiveLoading();
        if (!this.state.emergency)
          return { alreadyUnlocked: true, state: this.state.operationalState };
      }
      throw new Error(
        result.error || "Não foi possível desbloquear a máquina.",
      );
    }
    await this.loadActiveLoading();
    this.state.plcCommand = result.data;
    return result.data;
  }
  async finishLoading(justification = "") {
    if (!this.state.loadingId)
      throw new Error("Nenhum carregamento ativo encontrado.");
    const response = await fetch("/api/encerrar_carregamento.php", {
      method: "POST",
      headers: this.jsonHeaders(),
      body: JSON.stringify({ carregamento_id: this.state.loadingId, justification }),
    });
    const result = await response.json();
    if (!response.ok)
      throw new Error(
        result.error || "Não foi possível finalizar o carregamento.",
      );
    this.state.operationalState = "FINALIZADO";
    this.state.running = false;
    await this.loadMonitoring();
  }
  async loadPendingReadings() {
    if (!this.state.loadingId) {
      this.state.pendingReadings = [];
      return [];
    }
    const response = await fetch(`/api/leituras.php?carregamento_id=${encodeURIComponent(this.state.loadingId)}`);
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || "Não foi possível carregar leituras pendentes.");
    this.state.pendingReadings = result.data || [];
    return this.state.pendingReadings;
  }
  async identifyReading(readingId, barcode) {
    const response = await fetch("/api/identificar_leitura.php", { method: "POST", headers: this.jsonHeaders(), body: JSON.stringify({ leitura_id: readingId, barcode }) });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || "Não foi possível identificar a leitura.");
    await this.loadPendingReadings();
    return result.data;
  }
  async cancelManifest(manifestId, justification = "") {
    const response = await fetch("/api/romaneios.php", {
      method: "PATCH",
      headers: this.jsonHeaders(),
      body: JSON.stringify({ romaneio_id: manifestId, ...(justification ? { action: "cancel", justification } : {}) }),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || "Não foi possível cancelar o romaneio.");
    await this.loadManifests();
    return result.data;
  }
  async registerReturn(readingId, reason) {
    const response = await fetch("/api/retornos.php", { method: "POST", headers: this.jsonHeaders(), body: JSON.stringify({ carregamento_id: this.state.loadingId, leitura_id: readingId, reason }) });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(result.error || "Não foi possível registrar o retorno.");
    await this.loadActiveLoading(this.state.loadingId);
    return result.data;
  }
  async loadMonitoring() {
    const response = await fetch("/api/monitoramento.php");
    if (response.ok) this.state.monitoring = (await response.json()).data;
  }
  async loadSyncStatus() {
    const response = await fetch("/api/sync_status.php");
    const result = await response.json().catch(() => ({}));
    if (!response.ok)
      throw new Error(
        result.error || "Não foi possível carregar a fila de sincronização.",
      );
    this.state.syncStatus = result.data;
  }
  async retrySync(id) {
    const response = await fetch("/api/sync_queue.php", {
      method: "POST",
      headers: this.jsonHeaders(),
      body: JSON.stringify({ id }),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok && response.status !== 202)
      throw new Error(result.error || "Não foi possível reprocessar o evento.");
    await this.loadSyncStatus();
    return result.data;
  }
  async loadProducts() {
    if (this.productsRequest) return this.productsRequest;
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 10000);
    this.productsRequest = fetch("/api/produtos.php", { signal: controller.signal })
      .then(async (response) => {
        const result = await response.json().catch(() => ({}));
        if (!response.ok) {
          throw new Error(result.error || "Não foi possível carregar os produtos.");
        }
        this.state.products = Array.isArray(result.data) ? result.data : [];
        this.state.productsLoaded = true;
        return this.state.products;
      })
      .catch((error) => {
        if (error.name === "AbortError") {
          throw new Error("O carregamento dos produtos demorou. Tente novamente.");
        }
        throw error;
      })
      .finally(() => {
        window.clearTimeout(timeout);
        this.productsRequest = null;
      });
    return this.productsRequest;
  }
  async loadConfiguration() {
    const response = await fetch("/api/configuracoes.php");
    if (response.ok) this.state.configuration = (await response.json()).data;
  }
  async loadEquipments() {
    if (this.state.equipmentsLoading) return this.state.equipments;
    this.state.equipmentsLoading = true;
    this.state.equipmentsError = "";
    try {
      const response = await fetch("/api/equipamentos.php");
      const result = await response.json().catch(() => ({}));
      if (!response.ok) {
        this.state.equipmentsError =
          result.error || "Não foi possível carregar as Dalas.";
        return this.state.equipments;
      }
      this.state.equipments = Array.isArray(result.data) ? result.data : [];
      return this.state.equipments;
    } catch (error) {
      this.state.equipmentsError =
        error.message || "Não foi possível carregar as Dalas.";
      return this.state.equipments;
    } finally {
      this.state.equipmentsLoaded = true;
      this.state.equipmentsLoading = false;
    }
  }
  async loadCompanies() {
    const response = await fetch("/api/empresas.php");
    const result = await response.json();
    if (!response.ok)
      throw new Error(result.error || "Não foi possível carregar as empresas.");
    this.state.companies = result.data;
  }
  async loadUsers() {
    const companyId = this.state.selectedCompanyId;
    const query = companyId
      ? `?company_id=${encodeURIComponent(companyId)}`
      : "";
    const response = await fetch(`/api/usuarios.php${query}`);
    if (response.status === 422 && this.state.userRole === "ADMIN_DALLOGIX") {
      this.state.users = [];
      return;
    }
    const result = await response.json().catch(() => ({}));
    if (!response.ok)
      throw new Error(result.error || "Não foi possível carregar os logins.");
    this.state.users = result.data || [];
  }
  async createUser(payload) {
    const response = await fetch("/api/usuarios.php", {
      method: "POST",
      headers: this.jsonHeaders(),
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok)
      throw new Error(result.error || "Não foi possível criar o login.");
    return result.data;
  }
  async updateUser(payload) {
    const response = await fetch("/api/usuarios.php", {
      method: "PUT",
      headers: this.jsonHeaders(),
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok)
      throw new Error(result.error || "Não foi possível atualizar o login.");
    await this.loadUsers();
    return result.data;
  }
  async createCompany(payload) {
    const response = await fetch("/api/empresas.php", {
      method: "POST",
      headers: this.jsonHeaders(),
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok)
      throw new Error(result.error || "Não foi possível criar a empresa.");
    return result.data;
  }
  async deleteCompany(id) {
    const response = await fetch(`/api/empresas.php?id=${encodeURIComponent(id)}`, {
      method: "DELETE",
      headers: this.jsonHeaders(),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok)
      throw new Error(result.error || "Não foi possível remover a empresa.");
    await this.loadCompanies();
    return result.data;
  }
  async updateLicense(payload) {
    const response = await fetch("/api/licencas.php", {
      method: "PUT",
      headers: this.jsonHeaders(),
      body: JSON.stringify(payload),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok)
      throw new Error(result.error || "Não foi possível atualizar a licença.");
    await this.loadCompanies();
    return result.data;
  }
  selectCompany(id) {
    this.state.selectedCompanyId = Number(id);
  }
  async loadCompanyDetail() {
    if (!this.state.selectedCompanyId)
      throw new Error("Nenhuma empresa selecionada.");
    const response = await fetch(
      `/api/empresas.php?company_id=${this.state.selectedCompanyId}`,
    );
    const result = await response.json();
    if (!response.ok)
      throw new Error(result.error || "Não foi possível carregar a empresa.");
    this.state.companyDetail = result.data;
  }
  async saveConfiguration(data) {
    const response = await fetch("/api/configuracoes.php", {
      method: "PUT",
      headers: this.jsonHeaders(),
      body: JSON.stringify(data),
    });
    const result = await response.json();
    if (!response.ok)
      throw new Error(
        result.error || "Não foi possível salvar a configuração.",
      );
    await this.loadConfiguration();
  }
  async createEquipment(data) {
    const response = await fetch("/api/equipamentos.php", {
      method: "POST",
      headers: this.jsonHeaders(),
      body: JSON.stringify(data),
    });
    const result = await response.json();
    if (!response.ok)
      throw new Error(result.error || "Não foi possível cadastrar a Dala.");
    await this.loadEquipments();
    return result.data;
  }
  async createOccurrence(data) {
    const response = await fetch("/api/ocorrencias.php", {
      method: "POST",
      headers: this.jsonHeaders(),
      body: JSON.stringify(data),
    });
    const result = await response.json();
    if (!response.ok)
      throw new Error(result.error || "Não foi possível salvar a ocorrência.");
    await this.loadMonitoring();
  }
  toggleRun() {
    this.state.running = !this.state.running;
    if (this.state.running)
      this.state.loaded = Math.min(this.state.planned, this.state.loaded + 1);
  }
  stop() {
    this.state.running = false;
  }
  toggleReturn() {
    this.state.returnMode = !this.state.returnMode;
  }
  async createProduct(data) {
    const response = await fetch("/api/produtos.php", {
      method: "POST",
      headers: this.jsonHeaders(),
      body: JSON.stringify(data),
    });
    const result = await response.json().catch(() => ({}));
    if (!response.ok) {
      const error = new Error(
        response.status === 401
          ? "Sua sessão expirou. Entre novamente para cadastrar o produto."
          : result.error || "Não foi possível cadastrar o produto.",
      );
      error.status = response.status;
      throw error;
    }
    await this.loadProducts();
  }
  activateEmergency() {
    this.state.emergency = true;
    this.state.operationalState = "EMERGENCIA";
    this.state.running = false;
    this.state.returnMode = false;
  }
}
