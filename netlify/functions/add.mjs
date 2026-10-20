import {readData,writeData,json,headers,cleanNote,validDate,today,limited,clientKey} from "./_shared.mjs";
export default async function(req){
 if(req.method!=="POST")return json({error:"Method tidak diizinkan."},405,headers());
 if(await limited(req,"add",10,600000))return json({error:"Terlalu sering menambah data. Coba lagi beberapa menit lagi."},429,headers());
 let b={}; try{const ct=req.headers.get("content-type")||""; b=ct.includes("application/json")?await req.json():Object.fromEntries(await req.formData())}catch{}
 if(String(b.website||"").trim()!=="")return json({ok:true},200,headers());
 const raw=String(b.count??"").trim(), count=/^\d+$/.test(raw)?Number(raw):NaN, date=String(b.date||""), note=cleanNote(b.note);
 const t=today(), min=new Date(Date.now()-365*86400000).toISOString().slice(0,10);
 if(!Number.isInteger(count)||count<1||count>100)return json({error:"Jumlah open harus angka 1 sampai 100."},400,headers());
 if(!validDate(date)||date>t||date<min)return json({error:"Tanggal tidak valid."},400,headers());
 const d=await readData(); d.entries.push({id:globalThis.crypto.randomUUID().slice(0,8),name:d.settings.name,count,date,note,price:Number(d.settings.price),profit:Number(d.settings.profit),ts:Date.now()});
 await writeData(d); return json({ok:true},200,headers());
}
export const config={path:"/.netlify/functions/add"};
