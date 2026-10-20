(function(){
const root=document.getElementById('adminContent');
const api='/\.netlify/functions/admin'.replace('\\','');
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
const rp=n=>'Rp '+Number(n||0).toLocaleString('id-ID');
const today=()=>new Date(new Date().getTime()-new Date().getTimezoneOffset()*60000).toISOString().slice(0,10);
async function req(method='GET',body=null){
  const o={method,headers:{}};
  if(body){o.headers['Content-Type']='application/json';o.body=JSON.stringify(body)}
  const r=await fetch(api,o); const d=await r.json().catch(()=>({error:'Server error'}));
  if(!r.ok) throw new Error(d.error||'Gagal');
  return d;
}
function msg(t,err=false){root.querySelector('#msg')?.remove(); if(t){const x=document.createElement('div');x.id='msg';x.className='al '+(err?'err':'');x.textContent=t;root.prepend(x)}}
async function start(){
 try{
   const d=await req();
   if(d.setup){showSetup();return}
   if(!d.logged){showLogin();return}
   showDash(d);
 }catch(e){root.innerHTML='<div class="box"><div class="al err">'+esc(e.message)+'</div></div>'}
}
function showSetup(){
 root.innerHTML=`<div class="box login adm"><h2>Buat akun admin</h2><p class="note">Langkah satu kali. Setelah dibuat, halaman ini berubah menjadi halaman login.</p><div id="msg"></div>
 <form id="f"><div class="f"><div><label>Username</label><input name="user" required></div><div><label>Password (min. 8 karakter)</label><input name="pass" type="password" required></div><div><label>Ulangi password</label><input name="pass2" type="password" required></div><button>Buat akun</button></div></form></div>`;
 root.querySelector('form').onsubmit=async e=>{e.preventDefault();const f=new FormData(e.target);try{const d=await post({action:'setup',user:f.get('user'),pass:f.get('pass'),pass2:f.get('pass2')});if(d.ok)start();}catch(x){msg(x.message,true)}}
}
function showLogin(){
 root.innerHTML=`<div class="box login adm"><h2>Login Admin</h2><div id="msg"></div><form id="f"><div class="f"><div><label>Username</label><input name="user" required autocomplete="username"></div><div><label>Password</label><input name="pass" type="password" required autocomplete="current-password"></div><button>Masuk</button></div></form></div>`;
 root.querySelector('form').onsubmit=async e=>{e.preventDefault();const f=new FormData(e.target);try{await post({action:'login',user:f.get('user'),pass:f.get('pass')});start()}catch(x){msg(x.message,true)}}
}
async function post(b){const r=await fetch(api,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b)});const d=await r.json().catch(()=>({}));if(!r.ok)throw new Error(d.error||'Gagal');return d}
function showDash(d){
 const s=d.data.settings;
 root.innerHTML=`<div class="top"><div><h1>${esc(s.title)} <span class="tag">admin</span></h1><p class="sub">Harga ${rp(s.price)} · untung ${rp(s.profit)} per 1x open</p></div><div><a class="btn" href="/" target="_blank">Lihat website</a> <button id="logout" class="o">Keluar</button></div></div>
 <div id="msg"></div>
 <div class="g"><div class="c"><small>Total open</small><b>${d.stats.opens.toLocaleString('id-ID')}</b></div><div class="c"><small>Total omzet</small><b>${rp(d.stats.rev)}</b></div><div class="c"><small>Total untung</small><b class="gn">${rp(d.stats.pro)}</b></div><div class="c"><small>Jumlah data</small><b>${d.data.entries.length}</b></div></div>
 <div class="box adm"><h2>Tambah data open</h2><form id="add"><div class="f"><div><label>Jumlah open</label><input name="count" type="number" min="1" max="100000" value="1" required></div><div><label>Tanggal</label><input name="date" type="date" value="${today()}" required></div><div><label>Catatan</label><input name="note" maxlength="120" placeholder="opsional"></div><button>Tambah</button></div></form></div>
 <div class="box"><h2>Data open</h2><input id="q" placeholder="Cari catatan…" style="margin-bottom:12px"><div class="ov"><table><thead><tr><th>Tanggal</th><th>Nama</th><th class="r">Open</th><th class="r">Total</th><th class="r">Untung</th><th>Catatan</th><th></th></tr></thead><tbody id="rows"></tbody></table></div><div class="em" id="empty">Belum ada data.</div><p style="margin:12px 0 0"><button id="csv" class="o">Unduh CSV</button></p></div>
 <div class="box"><h2>Pengaturan website</h2><form id="settings"><div class="f"><div><label>Judul website</label><input name="title" maxlength="60" value="${esc(s.title)}" required></div><div><label>Nama di data baru</label><input name="name" maxlength="40" value="${esc(s.name)}" required></div><div><label>Harga 1x open (Rp)</label><input name="price" type="number" min="0" value="${s.price}" required></div><div><label>Untung 1x open (Rp)</label><input name="profit" type="number" min="0" value="${s.profit}" required></div><button>Simpan</button></div></form><p class="note">Perubahan harga/untung/nama hanya berlaku untuk data baru. Data lama tetap memakai nilai saat dicatat.</p></div>
 <div class="box"><h2>Akun admin</h2><form id="account"><div class="f"><div><label>Username</label><input name="user" value="${esc(d.admin.user)}" required></div><div><label>Password saat ini</label><input name="cur" type="password" required></div><div><label>Password baru (kosongkan jika tetap)</label><input name="new" type="password"></div><button>Perbarui</button></div></form></div>`;
 renderRows(d.data.entries);
 root.querySelector('#logout').onclick=async()=>{await post({action:'logout'});start()};
 root.querySelector('#add').onsubmit=async e=>{e.preventDefault();const f=new FormData(e.target);try{await post({action:'add',count:f.get('count'),date:f.get('date'),note:f.get('note')});msg('Data ditambahkan.');start()}catch(x){msg(x.message,true)}};
 root.querySelector('#settings').onsubmit=async e=>{e.preventDefault();const f=new FormData(e.target);try{await post({action:'settings',title:f.get('title'),name:f.get('name'),price:f.get('price'),profit:f.get('profit')});msg('Pengaturan disimpan.');start()}catch(x){msg(x.message,true)}};
 root.querySelector('#account').onsubmit=async e=>{e.preventDefault();const f=new FormData(e.target);try{await post({action:'account',user:f.get('user'),cur:f.get('cur'),new:f.get('new')});msg('Akun diperbarui.');start()}catch(x){msg(x.message,true)}};
 root.querySelector('#q').oninput=e=>renderRows(d.data.entries,e.target.value);
 root.querySelector('#csv').onclick=()=>{window.location=api+'?export=csv'};
}
function renderRows(es,q=''){q=q.toLowerCase();const a=es.filter(e=>(e.name+' '+e.note+' '+e.date).toLowerCase().includes(q));const tb=root.querySelector('#rows');tb.innerHTML=a.map(e=>{const c=Number(e.count||0);return `<tr><td>${esc(fmt(e.date))}</td><td>${esc(e.name)}</td><td class="r">${c}</td><td class="r">${rp(c*Number(e.price||0))}</td><td class="r gn">${rp(c*Number(e.profit||0))}</td><td class="mu">${esc(e.note||'')}</td><td><button class="s" data-id="${esc(e.id)}">Hapus</button></td></tr>`}).join('');root.querySelector('#empty').hidden=a.length>0;tb.querySelectorAll('.s').forEach(b=>b.onclick=async()=>{if(confirm('Hapus data ini?')){try{await post({action:'delete',id:b.dataset.id});start()}catch(x){msg(x.message,true)}}})}
function fmt(s){const p=String(s).split('-'),m=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];return p.length===3?Number(p[2])+' '+m[Number(p[1])-1]+' '+p[0]:s}
start();
})();