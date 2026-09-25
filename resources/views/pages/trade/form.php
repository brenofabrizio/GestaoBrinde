<?php ob_start(); ?>
<div class="card card-body" x-data="tradeForm()" x-init="init()">
  <p class="text-muted">Informe indústria, brinde, quantidade e finalidade. O número do chamado é preenchido só se o pedido for aprovado. A NF é anexada quando o material chegar no CD.</p>
  <form x-ref="form" @submit.prevent="save">
    <div x-show="formError" class="alert alert-danger" x-text="formError" role="alert"></div>
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Indústria *</label>
        <select class="form-select" name="industry_id" x-model="f.industry_id" required>
          <option value="">Selecione</option>
          <template x-for="d in inds" :key="d.id"><option :value="d.id" x-text="d.name"></option></template>
        </select></div>
      <div class="col-md-6"><label class="form-label">Tipo de ação</label>
        <select class="form-select" x-model="f.action_type">
          <option value="campanha">Campanha</option>
          <option value="premiacao">Premiação</option>
          <option value="evento">Evento</option>
          <option value="feirao">Feirão</option>
          <option value="outro">Outro</option>
        </select></div>
      <div class="col-md-6"><label class="form-label">Descrição / finalidade *</label>
        <input class="form-control" name="purpose" x-model="f.purpose" required placeholder="Ex.: Air Fryer 5L — campanha setembro"></div>
      <div class="col-md-6"><label class="form-label">Para quem será entregue</label>
        <input class="form-control" name="recipient" x-model="f.recipient" placeholder="Vendedor, cliente, evento…"></div>
      <div class="col-md-6"><label class="form-label">Local de entrega</label>
        <input class="form-control" name="delivery_place" x-model="f.delivery_place" placeholder="CD Belford Roxo"></div>
      <div class="col-12"><label class="form-label">Observações</label>
        <textarea class="form-control" name="notes" x-model="f.notes"></textarea></div>
    </div>
    <h3 class="h6 mt-4">Brindes</h3>
    <input class="form-control mb-2" placeholder="Buscar brinde (físico, voucher, cartão…)" x-model="q" @input="search()">
    <div class="list-group mb-2">
      <template x-for="o in opts" :key="o.id">
        <button type="button" class="list-group-item" @click="add(o)" x-text="o.code + ' — ' + o.name"></button>
      </template>
    </div>
    <template x-for="(line, i) in items" :key="line.item_id">
      <div class="row g-2 align-items-center mb-2">
        <div class="col-md-5" x-text="line.name"></div>
        <div class="col-md-2"><input class="form-control" :name="'items.' + i + '.qty_requested'" type="number" min="1" x-model.number="line.qty_requested" placeholder="Qtd"></div>
        <div class="col-md-3"><input class="form-control" x-model="line.unit_value" placeholder="Valor unit. R$"></div>
        <div class="col-md-1 small text-muted" x-text="Api.fmt.money((Number(line.qty_requested)||0) * (Number(String(line.unit_value).replace(',','.'))||0))"></div>
        <div class="col-md-1"><button type="button" class="btn btn-sm btn-outline-danger" @click="items.splice(i,1)">×</button></div>
      </div>
    </template>
    <button class="btn btn-primary mt-3" :disabled="saving">Enviar solicitação de compra</button>
  </form>
</div>
<?php
$content = ob_get_clean();
ob_start(); ?>
<script>
function tradeForm() {
  return {
    f: { industry_id: '', purpose: '', recipient: '', action_type: 'campanha', delivery_place: 'CD Belford Roxo', notes: '' },
    inds: [], q: '', opts: [], items: [], saving: false, formError: '', t: null,
    async init() {
      try {
        this.inds = (await Api.get('/api/industries', { all: 1 })).data || [];
      } catch (e) {
        this.formError = 'Não foi possível carregar as indústrias. Atualize a página ou procure o Administrador.';
      }
    },
    search() {
      clearTimeout(this.t);
      this.t = setTimeout(async () => {
        if (!this.q) { this.opts = []; return; }
        try {
          this.opts = (await Api.get('/api/items/options', { q: this.q })).data || [];
        } catch (e) {
          this.opts = [];
          this.formError = e.message || 'Não foi possível buscar os brindes.';
        }
      }, 250);
    },
    add(o) {
      if (this.items.find(i => i.item_id === o.id)) return;
      this.items.push({ item_id: o.id, name: o.code + ' — ' + o.name, qty_requested: 1, unit_value: o.unit_value ?? '' });
      this.opts = []; this.q = '';
    },
    async save() {
      this.formError = '';
      UI.clearErrors(this.$refs.form);
      const errors = {};
      if (!this.f.industry_id) errors.industry_id = 'Selecione a indústria.';
      if (!String(this.f.purpose || '').trim()) errors.purpose = 'Informe a finalidade da solicitação.';
      if (this.items.length === 0) errors.items = 'Inclua pelo menos um brinde.';
      this.items.forEach((line, index) => {
        if (!Number.isInteger(Number(line.qty_requested)) || Number(line.qty_requested) < 1) {
          errors.items = 'Informe uma quantidade maior que zero para todos os brindes.';
          errors['items.' + index + '.qty_requested'] = 'Quantidade inválida.';
        }
      });
      if (Object.keys(errors).length) {
        this.formError = 'Verifique os campos destacados.';
        UI.fieldErrors(this.$refs.form, errors);
        return;
      }
      this.saving = true;
      try {
        const { data } = await Api.postIdem('/api/trade/requests', {
          ...this.f,
          industry_id: Number(this.f.industry_id),
          items: this.items.map(i => ({
            item_id: Number(i.item_id),
            qty_requested: Number(i.qty_requested),
            unit_value: i.unit_value === '' ? null : Number(String(i.unit_value).replace(',', '.'))
          }))
        });
        location.href = Api.url('/solicitacoes-trade/' + data.id);
      } catch (e) {
        if (e.code === 'VALIDATION_ERROR') {
          this.formError = e.message || 'Verifique os campos destacados.';
          UI.fieldErrors(this.$refs.form, e.fields);
        } else {
          this.formError = e.message || 'Não foi possível criar a solicitação.';
          UI.toast(this.formError, 'err');
        }
      } finally {
        this.saving = false;
      }
    }
  };
}
</script>
<?php
$scripts = ob_get_clean();
include BASE_PATH . '/resources/views/layouts/app.php';
