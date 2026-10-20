import {readData,writeData,json,headers,readAdmin,saveAdmin,hashPassword,checkPassword,auth,setSession,clearSession,limited,cleanNote,validDate,today} from "./_shared.mjs";
function uname(u){return /^[A-Za-z0-9_.-]{3,32}$/.test(u)}
function stats(entries){let opens=0,rev=0,pro=0;for(const e of entries){const c=Number(e.count||0);opens+=c;rev+=c*Number(e.price||0);pro+=c*Number(e.profit||0)}return{opens,rev,pro}}
function csv(d){let s="\uFEFFTanggal,Nama,Open,Harga/open,Untung/open,Total,Untung,Catatan\n";for(const e of d.entries){const c=Number(e.count||0), note=String(e.note||"").replace(/"/g,'""');s+=`"${e.date||""}","${String(e.name||"").replace(/"/g,'""')}",${c},${Number(e.price||0)},${Number(e.profit||0)},${c*Number(e.price||0)},${c*Number(e.profit||0)},"${note}"\n`}return s}
export default async function(req){
 const h=headers();
 if(req.method==="GET"){
   const u=new URL(req.url);
   if(u.searchParams.get("export")==="csv"){
     if(!auth(req))return json({error:"Tidak diizinkan."},401,h);
     const d=await readData(); return new Response(csv(d),{status:200,headers:{"content-type":"text/csv; charset=utf-8","content-disposition":`attachment; filename="data-open-${today().replaceAll("-","")}.csv"`,...h}});
   }
   const a=await readAdmin();
   if(!a)return json({setup:true,logged:false},200,h);
   const session=auth(req);
   if(!session)return json({setup:false,logged:false},200,h);
   const d=await readData(); return json({setup:false,logged:true,admin:{user:a.user},data:d,stats:stats(d.entries)},200,h);
 }
 if(req.method!=="POST")return json({error:"Method tidak diizinkan."},405,h);
 let b={};try{b=await req.json()}catch{return json({error:"Request tidak valid."},400,h)}
 const action=String(b.action||"");
 if(action==="setup"){
   if(await readAdmin())return json({error:"Akun admin sudah dibuat."},409,h);
   const u=String(b.user||"").trim(), p=String(b.pass||""), p2=String(b.pass2||"");
   if(!uname(u))return json({error:"Username 3-32 karakter (huruf, angka, _ . -)."},400,h);
   if(p.length<8)return json({error:"Password minimal 8 karakter."},400,h);
   if(p!==p2)return json({error:"Konfirmasi password tidak sama."},400,h);
   const hp=await hashPassword(p);await saveAdmin({user:u,...hp});return new Response(JSON.stringify({ok:true}),{status:200,headers:{"content-type":"application/json", "set-cookie":setSession({user:u}),...h}});
 }
 if(action==="login"){
   if(await limited(req,"login",5,900000))return json({error:"Terlalu banyak percobaan gagal. Coba lagi 15 menit lagi."},429,h);
   const a=await readAdmin(); if(!a)return json({error:"Buat akun admin terlebih dahulu."},400,h);
   if(a.user!==String(b.user||"")||!(await checkPassword(String(b.pass||""),a)))return json({error:"Username atau password salah."},401,h);
   return new Response(JSON.stringify({ok:true}),{status:200,headers:{"content-type":"application/json","set-cookie":setSession({user:a.user}),...h}});
 }
 if(action==="logout")return new Response(JSON.stringify({ok:true}),{status:200,headers:{"content-type":"application/json","set-cookie":clearSession(),...h}});
 if(!auth(req))return json({error:"Sesi admin habis. Silakan login lagi."},401,h);
 const d=await readData();
 if(action==="add"){
   const count=/^\d+$/.test(String(b.count??""))?Number(b.count):NaN;
   let date=String(b.date||""); if(!Number.isInteger(count)||count<1||count>100000)return json({error:"Jumlah open harus angka 1 sampai 100000."},400,h);
   if(!validDate(date))date=today();
   d.entries.push({id:globalThis.crypto.randomUUID().slice(0,8),name:d.settings.name,count,date,note:cleanNote(b.note),price:Number(d.settings.price),profit:Number(d.settings.profit),ts:Date.now()});
   await writeData(d);return json({ok:true},200,h);
 }
 if(action==="delete"){const id=String(b.id||"");d.entries=d.entries.filter(e=>e.id!==id);await writeData(d);return json({ok:true},200,h)}
 if(action==="settings"){
   const title=cleanNote(b.title).slice(0,60),name=cleanNote(b.name).slice(0,40),price=Number(b.price),profit=Number(b.profit);
   if(!title||!name||!Number.isInteger(price)||price<0||price>100000000||!Number.isInteger(profit)||profit<0||profit>100000000)return json({error:"Judul, nama, harga, dan untung harus diisi dengan benar."},400,h);
   d.settings={title,name,price,profit};await writeData(d);return json({ok:true},200,h);
 }
 if(action==="account"){
   const a=await readAdmin(),u=String(b.user||"").trim(),cur=String(b.cur||""),nw=String(b.new||"");
   if(!(await checkPassword(cur,a)))return json({error:"Password saat ini salah."},400,h);
   if(!uname(u))return json({error:"Username 3-32 karakter (huruf, angka, _ . -)."},400,h);
   if(nw&&nw.length<8)return json({error:"Password baru minimal 8 karakter."},400,h);
   const hp=nw?await hashPassword(nw):{salt:a.salt,hash:a.hash};await saveAdmin({user:u,...hp});
   return new Response(JSON.stringify({ok:true}),{status:200,headers:{"content-type":"application/json","set-cookie":setSession({user:u}),...h}});
 }
 return json({error:"Aksi tidak dikenal."},400,h);
}
export const config={path:"/.netlify/functions/admin"};
