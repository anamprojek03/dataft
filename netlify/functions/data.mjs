import {readData,json,headers} from "./_shared.mjs";
export default async function(){const d=await readData();const entries=[...d.entries].reverse().slice(0,500);const totalOpen=d.entries.reduce((n,e)=>n+Number(e.count||0),0);return json({settings:d.settings,entries,totalOpen,entriesCount:d.entries.length,entriesTotal:d.entries.length},200,headers())}
export const config={path:"/.netlify/functions/data"};
