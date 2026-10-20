import { getStore } from "@netlify/blobs";
import crypto from "node:crypto";

export const DEFAULT_DATA = {"settings":{"title":"Data Open FT","name":"Noval","price":2000,"profit":1000},"entries":[{"id":"mvgmuju8","name":"Noval","count":1,"date":"2026-10-20","note":"","price":1500,"profit":1000},{"id":"mvgmuytc","name":"Noval","count":1,"date":"2026-10-20","note":"","price":1500,"profit":1000},{"id":"mvgmv5hz","name":"Noval","count":1,"date":"2026-10-20","note":"","price":1500,"profit":1000},{"id":"mvgmvkbc","name":"Noval","count":1,"date":"2026-10-20","note":"","price":2000,"profit":1000},{"id":"mvgmwtao","name":"Noval","count":1,"date":"2026-10-20","note":"","price":2000,"profit":1000},{"id":"mvgmwv48","name":"Noval","count":1,"date":"2026-10-20","note":"","price":2000,"profit":1000}]};

export function store(){ return getStore({name:"datanamz", consistency:"strong"}); }
export async function readData(){
  const s=store(); const d=await s.get("data",{type:"json"});
  if(!d){ await s.setJSON("data", DEFAULT_DATA); return structuredClone(DEFAULT_DATA); }
  d.settings={...DEFAULT_DATA.settings,...(d.settings||{})}; d.entries=Array.isArray(d.entries)?d.entries:[];
  return d;
}
export async function writeData(d){ await store().setJSON("data",d); }
export function json(data,status=200,headers={}){
 return new Response(JSON.stringify(data),{status,headers:{"content-type":"application/json; charset=utf-8","cache-control":"no-store",...headers}});
}
export function cleanNote(v){return String(v??"").replace(/[\x00-\x1F\x7F]/g," ").trim().slice(0,120)}
export function validDate(v){const m=/^\d{4}-\d{2}-\d{2}$/.test(v); if(!m)return false; const d=new Date(v+"T00:00:00Z"); return d.toISOString().slice(0,10)===v}
export function today(){return new Intl.DateTimeFormat("en-CA",{timeZone:"Asia/Jakarta"}).format(new Date())}
export function clientKey(req){return crypto.createHash("sha1").update(req.headers.get("x-nf-client-connection-ip")||req.headers.get("x-forwarded-for")||"x").digest("hex")}
export async function limited(req,bucket,max,windowMs){
 const s=store(), k="limit:"+bucket+":"+clientKey(req), now=Date.now();
 let a=await s.get(k,{type:"json"})||[]; a=a.filter(t=>now-t<windowMs);
 if(a.length>=max)return true; a.push(now); await s.setJSON(k,a,{ttl:windowMs+60000}); return false;
}
export function secret(){return process.env.SESSION_SECRET||""}
export function sign(payload){
 const b=Buffer.from(JSON.stringify(payload)).toString("base64url");
 const h=crypto.createHmac("sha256",secret()).update(b).digest("base64url");
 return b+"."+h;
}
export function verify(token){
 if(!secret()||!token)return null;
 const [b,h]=token.split("."); if(!b||!h)return null;
 const exp=crypto.createHmac("sha256",secret()).update(b).digest("base64url");
 if(!crypto.timingSafeEqual(Buffer.from(h),Buffer.from(exp)))return null;
 try{const p=JSON.parse(Buffer.from(b,"base64url").toString()); return p.exp>Date.now()?p:null}catch{return null}
}
export function auth(req){return verify((req.headers.get("cookie")||"").match(/datanamz_session=([^;]+)/)?.[1]||"")}
export function setSession(p){return `datanamz_session=${sign({...p,exp:Date.now()+86400000})}; Path=/; HttpOnly; SameSite=Lax; Secure; Max-Age=86400`}
export function clearSession(){return "datanamz_session=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax; Secure"}
export async function readAdmin(){return await store().get("admin",{type:"json"})}
export async function saveAdmin(a){await store().setJSON("admin",a)}
export async function hashPassword(pass){
 const salt=crypto.randomBytes(16).toString("hex");
 const hash=await new Promise((resolve,reject)=>crypto.pbkdf2(pass,salt,210000,32,"sha256",(e,b)=>e?reject(e):resolve(b.toString("hex"))));
 return {salt,hash}
}
export async function checkPassword(pass,rec){
 if(!rec?.salt||!rec?.hash)return false;
 const hash=await new Promise((resolve,reject)=>crypto.pbkdf2(pass,rec.salt,210000,32,"sha256",(e,b)=>e?reject(e):resolve(b.toString("hex"))));
 return crypto.timingSafeEqual(Buffer.from(hash),Buffer.from(rec.hash));
}
export function headers(){return {"cache-control":"no-store","x-content-type-options":"nosniff","x-frame-options":"DENY","referrer-policy":"same-origin","content-security-policy":"default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'none"}}
