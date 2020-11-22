<?php
namespace SMA\PAA\TOOL;

class MessageAuthenticationTools
{
    private $lastNonce;
    private $lastTimestamp;

    public function __construct()
    {
        $this->lastNonce = "";
        $this->lastTimestamp = "";
    }

    public function createNonce(int $length): string
    {
        $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";

        if ($length < 1) {
            throw new RangeException("Length must be positive integer");
        }

        $res = [];
        $max = mb_strlen($chars, "8bit") - 1;
        for ($i = 0; $i < $length; ++$i) {
            $res []= $chars[random_int(0, $max)];
        }

        $this->lastNonce = implode("", $res);
        
        return $this->lastNonce;
    }

    public function createTimestamp(): string
    {
        $this->lastTimestamp = strval(time());

        return $this->lastTimestamp;
    }

    public function createSha256Signature(string $data, string $key): string
    {
        return base64_encode(hash_hmac("sha256", $data, $key, true));
    }

    public function createVisSignature(
        string $appId,
        string $apiKey,
        string $requestMethod,
        string $rawUrl,
        string $content = ""
    ): string {
        $res = "";
        $res .= $appId;
        $res .= $requestMethod;
        $res .= strtolower(rawurlencode($rawUrl));
        $res .= $this->createTimestamp();
        $res .= $this->createNonce(32);
        if ($content === "") {
            $res .= $content;
        } else {
            $res .= base64_encode(md5($content, true));
        }

        $res = $this->createSha256Signature($res, $apiKey);

        return $res;
    }

    public function createVisAuthorizationHeader(
        string $appId,
        string $apiKey,
        string $requestMethod,
        string $rawUrl,
        string $content = ""
    ): string {
        $res = "amx ";
        $res .= $appId;
        $res .= ":" . $this->createVisSignature($appId, $apiKey, $requestMethod, $rawUrl, $content);
        $res .= ":" . $this->lastNonce;
        $res .= ":" . $this->lastTimestamp;

        return $res;
    }

    public function createRandomUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $id = str_split(bin2hex($bytes), 4);

        return "{$id[0]}{$id[1]}-{$id[2]}-{$id[3]}-{$id[4]}-{$id[5]}{$id[6]}{$id[7]}";
    }
}
