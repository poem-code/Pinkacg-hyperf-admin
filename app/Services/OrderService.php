<?php


namespace App\Services;


use App\Exception\RequestException;
use App\Filters\OrderFilter;
use App\Model\Order;
use App\Model\Post;
use App\Model\User;
use App\Resource\admin\OrderResource;
use Hyperf\Di\Annotation\Inject;
use Psr\Http\Message\ResponseInterface;

class OrderService extends Service
{
    /**
     * @Inject
     * @var OrderFilter
     */
    protected $orderFilter;

    /**
     * @param $request
     * @return ResponseInterface
     */
    public function index($request): ResponseInterface
    {
        $orderBy = $request->input('orderBy', 'id');
        $pageSize = $request->query('pageSize') ?? 12;

        //获取数据
        try {
            $order = Order::query()
                ->where($this->orderFilter->apply())
                ->orderBy($orderBy, 'asc')
                ->paginate((int)$pageSize, ['*'], 'pageNo');
            return $this->success(self::getDisplayColumnData(OrderResource::collection($order), $request, $order));
        } catch (\Throwable $throwable) {
            throw new RequestException($throwable->getMessage(), $throwable->getCode());
        }
    }

    /**
     * @param $request
     * @return ResponseInterface
     */
    public function create($request): ResponseInterface
    {
        //获取验证数据
        $data = self::getValidatedData($request);

        //创建内容
        try {
            $flag = Order::query()->create($data);
        } catch (\Throwable $throwable) {
            throw new RequestException($throwable->getMessage(), $throwable->getCode());
        }

        //返回结果
        if ($flag) {
            return $this->success();
        }
        return $this->fail();
    }

    /**
     * @param $request
     * @param $id
     * @return ResponseInterface
     */
    public function update($request, $id): ResponseInterface
    {
        //获取验证数据
        $data = self::getValidatedData($request);

        //更新内容
        try {
            $flag = Order::query()->where('id', $id)->update($data);
        } catch (\Throwable $throwable) {
            throw new RequestException($throwable->getMessage(), $throwable->getCode());
        }

        //返回结果
        if ($flag) {
            return $this->success();
        }
        return $this->fail();
    }

    /**
     * @param $id
     * @return ResponseInterface
     */
    public function delete($id): ResponseInterface
    {
        //删除内容
        try {
            $flag = Order::query()->where('id', $id)->delete();
        } catch (\Throwable $throwable) {
            throw new RequestException($throwable->getMessage(), $throwable->getCode());
        }

        //返回结果
        if ($flag) {
            return $this->success();
        }
        return $this->fail();
    }

    /**
     * @param $request
     * @return ResponseInterface
     */
    public function purchase($request): ResponseInterface
    {
        $data = $request->all();

        if (isset($data['credit']) && isset($data['download_key']) && isset($data['post_id']) && isset($data['user_id'])) {
            //购买文章
            try {
                //判断积分
                $credit = (User::query()->select('credit')->where('id', $data['user_id'])->get())[0]['credit'];
                if ($credit < $data['credit'] || $credit <= 0) {
                    return $this->fail([], '积分不够');
                }

                $flag = Order::query()->insert([
                    'user_id' => $data['user_id'],
                    'post_id' => $data['post_id'],
                    'type' => 'post',
                    'download_key' => $data['download_key'],
                    'credit' => $data['credit'],
                ]);

                //扣取积分
                $credit = $credit - $data['credit'];
                User::query()->where('id', $data['user_id'])->update([
                    'credit' => $credit
                ]);
                if ($flag) {
                    $download = \Qiniu\json_decode((Post::query()->select('download')->where('id', $data['post_id'])->get()->toArray())[0]['download'])[$data['download_key']];
                    return $this->success(['data' => $download]);
                }
            } catch (\Throwable $throwable) {
                throw new RequestException($throwable->getMessage(), $throwable->getCode());
            }
        }
        return $this->fail();
    }
}