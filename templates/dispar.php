<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap" id="was-disparo-app">
    <h1>Disparo</h1>
    <p class="description">Envie um template aprovado para uma lista de contatos, associando as variáveis às colunas da planilha.</p>

    <div class="was-dispar-grid">
        <section class="was-dispar-card">
            <h2>1. Preparar disparo</h2>
            <label>Template<br><select id="dispar-template"><option>Carregando...</option></select></label>
            <div id="dispar-variable-help" class="was-dispar-help">Selecione um template para ver as variáveis.</div>
            <label>CSV com contatos<br><input id="dispar-csv" type="file" accept=".csv,text/csv"></label>
            <p class="description">A primeira coluna deve ser <code>phone</code> ou <code>telefone</code>. As demais colunas devem usar os nomes das variáveis do template, como <code>nome</code> e <code>compra</code>.</p>
            <label>Intervalo entre mensagens (segundos)<br><input id="dispar-interval" type="number" min="5" value="60"></label>
            <label>Custo estimado por mensagem (R$)<br><input id="dispar-cost" type="number" min="0" step="0.000001" value="0.00"><small>Valor configurável para estimativa interna; a cobrança real depende da Meta.</small></label>
            <button id="dispar-preview" class="button button-primary" disabled>Validar planilha e continuar</button>
        </section>

        <section class="was-dispar-card" id="dispar-confirm" style="display:none">
            <h2>2. Confirmar</h2><div id="dispar-summary"></div>
            <p><strong>O envio só começa ao confirmar.</strong> Cada linha será marcada como enviada ou falha, com a mensagem de erro retornada pela API.</p>
            <button id="dispar-create" class="button button-primary">Criar disparo</button>
        </section>
    </div>

    <section class="was-dispar-card" id="dispar-running" style="display:none;margin-top:20px">
        <div class="was-dispar-toolbar"><h2>3. Acompanhamento</h2><button id="dispar-pause" class="button">Pausar</button></div>
        <div class="was-dispar-stats"><span><b id="dispar-total">0</b> total</span><span><b id="dispar-sent">0</b> enviados</span><span><b id="dispar-failed">0</b> falhas</span><span><b id="dispar-pending">0</b> pendentes</span><span><b id="dispar-cost-total">R$ 0,00</b> estimado</span></div>
        <div class="was-dispar-progress"><i id="dispar-progress-bar"></i></div><p id="dispar-status-text"></p>
        <table class="wp-list-table widefat striped"><thead><tr><th>Telefone</th><th>Status</th><th>Mensagem/erro</th><th>Data</th></tr></thead><tbody id="dispar-items"></tbody></table>
    </section>
    <section class="was-dispar-card" style="margin-top:20px"><h2>Histórico</h2><table class="wp-list-table widefat striped"><thead><tr><th>Data</th><th>Template</th><th>Status</th><th>Resultado</th></tr></thead><tbody id="dispar-history"><tr><td colspan="4">Carregando...</td></tr></tbody></table></section>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('was-disparo-app'); if (!root || !window.wasApp) return;
    const api = async (path, method='GET', body=null) => { const o={method,headers:{'X-WP-Nonce':wasApp.nonce,'Accept':'application/json'}}; if(body){o.headers['Content-Type']='application/json';o.body=JSON.stringify(body)} const r=await fetch(wasApp.restUrl+path,o); const d=await r.json(); if(!r.ok) throw new Error(d.message||d.error||'Erro na API'); return d; };
    const templateSelect=document.getElementById('dispar-template'), csvInput=document.getElementById('dispar-csv'), previewBtn=document.getElementById('dispar-preview');
    let templates=[], selected=null, parsedRows=[], broadcastId=null, timer=null, paused=false;
    const esc = v => String(v??'').replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
    const parseCsv = text => { const rows=[]; let row=[], cell='', quote=false; for(let i=0;i<text.length;i++){const c=text[i],n=text[i+1]; if(c==='"'&&quote&&n==='"'){cell+='"';i++;continue} if(c==='"'){quote=!quote;continue} if(c===','&&!quote){row.push(cell.trim());cell='';continue} if((c==='\n'||c==='\r')&&!quote){if(c==='\r'&&n==='\n')i++;row.push(cell.trim());if(row.some(Boolean))rows.push(row);row=[];cell='';continue} cell+=c;} if(cell||row.length){row.push(cell.trim());rows.push(row)} return rows; };
    const varsOf = t => { try { const map=JSON.parse(t.variable_map||'{}'); return Object.values(map); } catch(e){ return []; } };
    const updateHelp = () => { selected=templates.find(t=>String(t.id)===templateSelect.value); const vars=selected?varsOf(selected):[]; document.getElementById('dispar-variable-help').innerHTML=selected ? (vars.length?'Variáveis obrigatórias: <strong>'+vars.map(esc).join(', ')+'</strong>.':'Este template não possui variáveis.'):'Selecione um template para ver as variáveis.'; };
    (async()=>{ try{templates=await api('/disparo/templates'); templateSelect.innerHTML=templates.filter(t=>String(t.status).toLowerCase()==='approved').map(t=>`<option value="${t.id}">${esc(t.name)} — ${esc(t.category||'')}</option>`).join('')||'<option>Nenhum template aprovado</option>'; updateHelp();}catch(e){templateSelect.innerHTML='<option>Erro ao carregar</option>';}})();
    templateSelect.addEventListener('change',updateHelp); csvInput.addEventListener('change',()=>previewBtn.disabled=!csvInput.files.length);
    previewBtn.addEventListener('click',()=>{ const file=csvInput.files[0]; if(!file||!selected)return; const reader=new FileReader(); reader.onload=()=>{try{const raw=parseCsv(reader.result.replace(/^\uFEFF/,'')); if(raw.length<2)throw Error('O CSV precisa ter cabeçalho e pelo menos uma linha.'); const headers=raw[0].map(h=>h.toLowerCase().replace(/\s+/g,'_')); const phoneIdx=headers.findIndex(h=>['phone','telefone','telefone_whatsapp','numero','número'].includes(h)); if(phoneIdx<0)throw Error('A primeira linha precisa ter uma coluna phone ou telefone.'); const vars=varsOf(selected); const missing=vars.filter(v=>!headers.includes(v.toLowerCase())); if(missing.length)throw Error('Colunas ausentes: '+missing.join(', ')); parsedRows=raw.slice(1).map(r=>({phone:r[phoneIdx]||'',variables:Object.fromEntries(vars.map(v=>[v,r[headers.indexOf(v.toLowerCase())]||'']))})); document.getElementById('dispar-summary').innerHTML=`<p><strong>${parsedRows.length}</strong> contatos serão processados com <strong>${esc(selected.name)}</strong>.</p><p>Intervalo: <strong>${esc(document.getElementById('dispar-interval').value)} segundos</strong> · Custo estimado total: <strong>R$ ${(parsedRows.length*parseFloat(document.getElementById('dispar-cost').value||0)).toFixed(2).replace('.',',')}</strong></p>`; document.getElementById('dispar-confirm').style.display='block';}catch(e){alert(e.message)}}; reader.readAsText(file); });
    document.getElementById('dispar-create').addEventListener('click',async()=>{try{const d=await api('/disparo','POST',{template_id:selected.id,rows:parsedRows,interval_seconds:parseInt(document.getElementById('dispar-interval').value,10)||60,cost_per_message:parseFloat(document.getElementById('dispar-cost').value)||0}); broadcastId=d.id; await api('/disparo/'+broadcastId+'/start','POST'); document.getElementById('dispar-confirm').style.display='none'; document.getElementById('dispar-running').style.display='block'; run();}catch(e){alert(e.message)}});
    document.getElementById('dispar-pause').addEventListener('click',async()=>{if(!broadcastId)return; if(!paused){await api('/disparo/'+broadcastId+'/pause','POST');paused=true;clearTimeout(timer);document.getElementById('dispar-pause').textContent='Retomar';}else{await api('/disparo/'+broadcastId+'/start','POST');paused=false;document.getElementById('dispar-pause').textContent='Pausar';run();}});
    async function run(){ if(paused)return; try{const r=await api('/disparo/'+broadcastId+'/process','POST'); await refresh(); if(!r.completed&&!paused) timer=setTimeout(run,Math.max(5000,(parseInt(document.getElementById('dispar-interval').value,10)||60)*1000));}catch(e){document.getElementById('dispar-status-text').textContent=e.message;timer=setTimeout(run,10000)} }
    async function refresh(){const d=await api('/disparo/'+broadcastId); const s=d.summary||{}; document.getElementById('dispar-total').textContent=d.total_count;document.getElementById('dispar-sent').textContent=s.sent||0;document.getElementById('dispar-failed').textContent=s.failed||0;document.getElementById('dispar-pending').textContent=(s.pending||0)+(s.sending||0);document.getElementById('dispar-cost-total').textContent='R$ '+((s.sent||0)*parseFloat(d.cost_per_message||0)).toFixed(2).replace('.',','); const done=(s.sent||0)+(s.failed||0),pct=d.total_count?done/d.total_count*100:0;document.getElementById('dispar-progress-bar').style.width=pct+'%';document.getElementById('dispar-status-text').textContent=d.status==='completed'?'Disparo concluído.':(d.status==='paused'?'Disparo pausado.':`Processando ${done} de ${d.total_count}...`);document.getElementById('dispar-items').innerHTML=(d.items||[]).slice(-100).reverse().map(i=>`<tr><td>${esc(i.phone)}</td><td>${i.status==='sent'?'✅ Enviado':i.status==='failed'?'❌ Falha':i.status}</td><td>${esc(i.error_message||i.wa_message_id||'—')}</td><td>${esc(i.sent_at||'—')}</td></tr>`).join('');}
    (async()=>{try{const list=await api('/disparo');document.getElementById('dispar-history').innerHTML=list.map(b=>`<tr><td>${esc(b.created_at)}</td><td>${esc(b.name||'—')}</td><td>${esc(b.status)}</td><td>${b.sent_count||0}/${b.total_count||0} enviados · ${b.failed_count||0} falhas</td></tr>`).join('')||'<tr><td colspan="4">Nenhum disparo criado.</td></tr>'}catch(e){}})();
});
</script>
