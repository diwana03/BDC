const https=require('https');
const urls=[
  'https://bachatadancecouncil.com/portal/mcp',
  'https://bachatadancecouncil.com/portal/mcp/',
  'https://bachatadancecouncil.com/portal/mcp/oauth/resource.php',
  'https://bachatadancecouncil.com/portal/.well-known/oauth-protected-resource',
  'https://bachatadancecouncil.com/portal/mcp/oauth/.well-known/oauth-authorization-server',
  'https://bachatadancecouncil.com/portal/.well-known/oauth-authorization-server'
];
function request(url,method='GET',body=''){
  return new Promise(resolve=>{
    const u=new URL(url);
    const req=https.request({hostname:u.hostname,path:u.pathname,method,headers:body?{'content-type':'application/json','content-length':Buffer.byteLength(body)}:{}},res=>{
      let data='';res.on('data',c=>data+=c);res.on('end',()=>resolve({url,method,status:res.statusCode,location:res.headers.location||'',type:res.headers['content-type']||'',authenticate:res.headers['www-authenticate']||'',body:data.slice(0,1200)}));
    });
    req.on('error',error=>resolve({url,method,error:error.message}));
    req.setTimeout(15000,()=>req.destroy(new Error('timeout')));
    if(body)req.write(body);req.end();
  });
}
(async()=>{
  for(const url of urls)console.log(JSON.stringify(await request(url)));
  console.log(JSON.stringify(await request('https://bachatadancecouncil.com/portal/mcp','POST',JSON.stringify({jsonrpc:'2.0',id:1,method:'initialize',params:{protocolVersion:'2025-06-18',capabilities:{},clientInfo:{name:'diagnostic',version:'1'}}}))));
})().catch(e=>{console.error(e);process.exitCode=1});
