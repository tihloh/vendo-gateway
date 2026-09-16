<?php
declare(strict_types=1);
namespace Tihloh\VendoGateway\Firmware;
final class ReleaseManifestService
{
    public function __construct(private string $manifestUrl='https://github.com/tihloh/vendogate-firmware-releases/releases/latest/download/manifest.json',private int $cacheSeconds=300,private string $releaseApiUrl='https://api.github.com/repos/tihloh/vendogate-firmware-releases/releases/latest') {}
    public function latest(?string $target=null): ?array
    {
        $release=$this->release();
        if($release){$version=ltrim((string)($release['tag_name']??''),'vV');if($version!==''){
            $result=['version'=>$version,'channel'=>'stable','hardware_model'=>'VG-VENDO-01','hardware_revision'=>'1','files'=>[]];
            foreach((array)($release['assets']??[]) as $asset){$name=(string)($asset['name']??'');if($name==='')continue;$assetTarget=$name==='vendogate-esp8266.bin'?'esp8266':($name==='vendogate-esp32.bin'?'esp32':null);if(!$assetTarget)continue;$digest=(string)($asset['digest']??'');$sha=str_starts_with($digest,'sha256:')?substr($digest,7):'';$size=(int)($asset['size']??0);$url=(string)($asset['browser_download_url']??'');$file=['target'=>$assetTarget,'file'=>$name,'sha256'=>$sha,'size'=>$size,'url'=>$url];$result['files'][]=$file;if($target===$assetTarget&&$sha!==''&&$size>0&&$url!=='')return $result+$file;}
            if($target===null)return $result;
        }}
        $manifest=$this->manifest();if(!$manifest)return null;
        $result=['version'=>(string)($manifest['version']??''),'channel'=>(string)($manifest['channel']??'stable'),'hardware_model'=>(string)($manifest['hardware_model']??''),'hardware_revision'=>(string)($manifest['hardware_revision']??''),'files'=>(array)($manifest['files']??[])];
        if($result['version']==='')return null;
        if($target===null)return $result;
        foreach($result['files'] as $file){if(($file['target']??null)!==$target)continue;$name=(string)($file['file']??'');$sha=(string)($file['sha256']??'');$size=(int)($file['size']??0);if($name===''||!preg_match('/^[a-f0-9]{64}$/i',$sha)||$size<1)return null;return $result+['target'=>$target,'file'=>$name,'sha256'=>strtolower($sha),'size'=>$size,'url'=>$this->binaryUrl($result['version'],$name)];}
        return null;
    }
    public function status(string $currentVersion,string $target): array
    {
        $release=$this->latest();$current=ltrim(trim($currentVersion),'vV');
        if(!$release)return ['current_version'=>$currentVersion,'latest_version'=>null,'update_available'=>null,'target'=>$target];
        $status=['current_version'=>$currentVersion,'latest_version'=>$release['version'],'update_available'=>version_compare($release['version'],$current,'>'),'target'=>$target,'channel'=>$release['channel']??'stable'];
        $binary=$this->latest($target);if($binary){$status['url']=$binary['url'];$status['sha256']=$binary['sha256'];$status['size']=$binary['size'];}
        return $status;
    }
    private function release(): ?array
    {
        $cache=sys_get_temp_dir().'/vendo-gateway-firmware-release-'.sha1($this->releaseApiUrl).'.json';
        if(is_file($cache)&&filemtime($cache)!==false&&time()-filemtime($cache)<$this->cacheSeconds){$data=json_decode((string)@file_get_contents($cache),true);if(is_array($data))return $data;}
        $raw=$this->fetch($this->releaseApiUrl);if($raw===null)return null;$data=json_decode($raw,true);if(!is_array($data)||empty($data['tag_name'])||!is_array($data['assets']??null))return null;@file_put_contents($cache,$raw,LOCK_EX);return $data;
    }
    private function manifest(): ?array
    {
        $cache=sys_get_temp_dir().'/vendo-gateway-firmware-manifest-'.sha1($this->manifestUrl).'.json';
        if(is_file($cache)&&filemtime($cache)!==false&&time()-filemtime($cache)<$this->cacheSeconds){$data=json_decode((string)@file_get_contents($cache),true);if(is_array($data))return $data;}
        $raw=$this->fetch($this->manifestUrl);if($raw===null)return null;$data=json_decode($raw,true);if(!is_array($data)||($data['protocol']??'')!=='vendo-gateway-firmware'||empty($data['version'])||!is_array($data['files']??null))return null;@file_put_contents($cache,$raw,LOCK_EX);return $data;
    }
    private function fetch(string $url): ?string
    {
        if(function_exists('curl_init')){$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>10,CURLOPT_USERAGENT=>'tihloh/vendo-gateway',CURLOPT_HTTPHEADER=>['Accept: application/vnd.github+json','X-GitHub-Api-Version: 2022-11-28']]);$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);if(is_string($raw)&&$raw!==''&&$code>=200&&$code<300)return $raw;}
        $context=stream_context_create(['http'=>['timeout'=>10,'follow_location'=>1,'header'=>"Accept: application/vnd.github+json\r\nX-GitHub-Api-Version: 2022-11-28\r\nUser-Agent: tihloh/vendo-gateway\r\n"]]);$raw=@file_get_contents($url,false,$context);return is_string($raw)&&$raw!==''?$raw:null;
    }
    private function binaryUrl(string $version,string $file): string{return 'https://github.com/tihloh/vendogate-firmware-releases/releases/download/v'.rawurlencode($version).'/'.rawurlencode($file);}
}
