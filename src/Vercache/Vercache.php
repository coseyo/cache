<?php

/**
 * 基于redis的版本缓存类
 *
 * 使用示例
 * ------------------------------------------------
 *
 * $cache = \App::make('vercache');
 * $version = 'version';
 * $prefix = 'prefix';
 * $versionKey = $cache->getVersionKey([
 * 'user' => 'seyo',
 * 'phone' => '1234',
 * 'page' => 1,
 * ], $version, ['user', 'phone']);
 *
 * $data = $cache->getCache($prefix, 'key_aa', $versionKey);
 * if ($data) {
 * var_dump('get cache');
 * var_dump($data);
 * } else {
 * var_dump('no cache');
 * $data = 'value_bb';
 * $cache->setCache($prefix, 'key_aa', $data, $versionKey);
 * }
 *
 * var_dump('incrVersionNum');
 * $cache->incrVersionNum($prefix, $versionKey);
 * ------------------------------------------------
 *
 * @author zhongyijun@wps.cn
 * @date 2015-12-10
 * Class VCache
 */
class Vercache
{
    /**
     * 缓存索引
     *
     * @var array
     */
    private $cachePrefix = array();

    /**
     * 缓存过期时间
     *
     * @var int
     */
    private $expireTime = 0;

    /**
     * redis 实例
     *
     * @var
     */
    private $redis;


    /**
     * 缓存模块初始化
     *
     * @param array $params
     */
    public function __construct($params = array())
    {
        $this->cachePrefix = $params['prefix'];
        $this->expireTime = $params['expire'];
        $is_pconnect = isset($params['is_pconnect']) ? $params['is_pconnect'] : 1;
        try {
            $this->redis = new redis();
            $method = $is_pconnect ? 'pconnect' : 'connect';
            $this->redis->{$method}($params['host'], $params['port'], $params['timeout']);
        } catch (RedisException $e) {
            show_error('Redis connection refused. ' . $e->getMessage());
        }
    }

    /**
     * 获取缓存前缀
     *
     * @param $prefix
     * @return string
     */
    private function getCachePrefix($prefix)
    {
        return $this->cachePrefix . ':' . $prefix . ':';
    }

    private function getKeyWithVersionNum($prefix, $key, $versionKey = NULL)
    {
        $versionKey && $key .= $this->getVersionNum($prefix, $versionKey);
        return md5($this->getCachePrefix($prefix) . $key);
    }


    /**
     * 获取缓存数据
     *
     * @param $prefix
     * @param $key
     * @param null $versionKey
     * @return array|bool|mixed|stdClass|string
     */
    public function getCache($prefix, $key, $versionKey = NULL)
    {
        $key = $this->getKeyWithVersionNum($prefix, $key, $versionKey);
        $resp = $this->redis->get($key);
        if ($resp) {
            $resp = json_decode($resp, true);
        }
        return $resp;
    }

    /**
     * 设置缓存数据
     *
     * @param $prefix
     * @param $key
     * @param $info
     * @param null $versionKey
     * @param null $expireTime
     * @return bool
     */
    public function setCache($prefix, $key, $info, $versionKey = null, $expireTime = null)
    {
        $expire = $expireTime !== null ? $expireTime : $this->expireTime;
        $key = $this->getKeyWithVersionNum($prefix, $key, $versionKey);
        $info = json_encode($info);
        return $this->redis->setex($key, $expire, $info);
    }

    /**
     * 删除缓存
     *
     * @param $prefix
     * @param $key
     * @param null $versionKey
     * @return bool
     */
    public function delCache($prefix, $key, $versionKey = null)
    {
        $key = $this->getKeyWithVersionNum($prefix, $key, $versionKey);
        return $this->redis->delete($key) === 1;
    }

    /**
     * 更新缓存版本号，加1
     *
     * @param $prefix
     * @param $versionKey
     */
    public function incrVersionNum($prefix, $versionKey)
    {
        $key = $this->getCachePrefix($prefix) . $versionKey;
        $versionNum = $this->redis->get($key);
        $versionNum++;
        $this->redis->setex($key, 7200, $versionNum);
    }

    /**
     * 获取缓存版本号的值
     *
     * @param $prefix
     * @param $versionKey
     * @return int
     */
    public function getVersionNum($prefix, $versionKey)
    {
        $key = $this->getCachePrefix($prefix) . $versionKey;
        $versionNum = $this->redis->get($key);
        if (FALSE === $versionNum) {
            $versionNum = 1;
        }
        return $versionNum;
    }

    /**
     * 获取缓存版本的key
     * 例子：
     * $params = array(
     *  'user' => 'wps'
     *  'state' => 1
     *  'page' => 1,
     *  'limit' => 10
     * )
     *
     * 如果数据库修改state 为 2后，需要更新这个用户相关的缓存，所有翻页的数据都需要随之更新，这时需要获取版本缓存key的方式是
     *
     * getVersionKey($params, 'prefix_test', 'user');
     *
     * state, page, limit 都不应该作为缓存版本key的一部分。
     *
     * @param array $params 参数数组
     * @param string $prefix key前缀
     * @param array $onlyParams 需要以 $params 数组中的key作为version_key的一部分的key数组
     * @param array $exceptParams 排除数组中的key
     * @return string
     */
    public function getVersionKey(array $params, $prefix = '', $onlyParams = array(), $exceptParams = array())
    {
        $versionKey = $prefix;
        ksort($params);
        if ($onlyParams) {
            ksort($onlyParams);
            foreach ($onlyParams as $v) {
                if (isset($params[$v])) {
                    $versionKey .= "_{$v}-{$params[$v]}";
                }
            }
            return $versionKey;
        }

        if ($exceptParams) {
            foreach ($exceptParams as $v) {
                if (isset($params[$v])) {
                    unset($params[$v]);
                }
            }
        }

        foreach ($params as $k => $v) {
            $versionKey .= "_{$k}-{$v}";
        }

        return $versionKey;
    }
}
