(function(){
  const $=id=>document.getElementById(id);
  const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const rp=n=>'Rp '+Number(n||0).toLocaleString('id-ID');
  const today=new Date();
  $('date').value=new Date(today.getTime()-today.getTimezoneOffset()*60000).toISOString().slice(0,10);

  let rows=[];
  function render(){
    const q=$('q').value.trim().toLowerCase();
    const shown=rows.filter(x=>(x.name+' '+x.note+' '+x.date).toLowerCase().includes(q));
    $('rows').innerHTML=shown.map(en=>`<tr><td>${esc(formatDate(en.date))}</td><td>${esc(en.name)}</td><td class="r">${Number(en.count||0)}</td><td class="mu">${esc(en.note||'')}</td></tr>`).join('');
    $('empty').hidden=shown.length!==0;
  }
  function formatDate(s){
    const p=String(s).split('-'); if(p.length!==3)return s||'-';
    const m=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    return Number(p[2])+' '+m[Number(p[1])-1]+' '+p[0];
  }
  async function load(){
    try{
      const r=await fetch('/.netlify/functions/data',{cache:'no-store'});
      const d=await r.json(); if(!r.ok) throw new Error(d.error||'Gagal memuat data');
      $('title').textContent=d.settings.title;
      $('price').textContent=rp(d.settings.price);
      $('totalOpen').textContent=Number(d.totalOpen).toLocaleString('id-ID');
      $('totalEntries').textContent=d.entriesCount;
      rows=d.entries;
      $('limitNote').hidden=d.entriesTotal<=500;
      render();
    }catch(e){ showErr(e.message); }
  }
  function showErr(m){$('err').textContent=m;$('err').hidden=false;$('ok').hidden=true;}
  $('q').addEventListener('input',render);
  $('addForm').addEventListener('submit',async e=>{
    e.preventDefault(); $('err').hidden=true;$('ok').hidden=true;
    const fd=new FormData(e.target);
    try{
      const r=await fetch('/.netlify/functions/add',{method:'POST',body:fd});
      const d=await r.json(); if(!r.ok) throw new Error(d.error||'Gagal menyimpan data.');
      $('ok').hidden=false; e.target.reset();
      $('count').value='1'; $('date').value=new Date(new Date().getTime()-new Date().getTimezoneOffset()*60000).toISOString().slice(0,10);
      await load();
    }catch(x){showErr(x.message);}
  });
  load();
})();