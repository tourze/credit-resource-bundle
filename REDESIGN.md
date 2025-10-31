# Credit Resource Bundle 重构设计文档

## 模块定位

`credit-resource-bundle` 是基于 `credit-bundle` 构建的资源计费和账单管理系统。它专注于：

1. **资源计费规则定义** - 灵活定义各种资源的计费策略
2. **资源使用量统计** - 自动统计各种资源的使用情况
3. **账单生成与管理** - 生成、查询和管理资源账单
4. **自动扣费集成** - 与 credit-bundle 无缝集成，实现自动扣费

## 与 credit-bundle 的关系

```
┌─────────────────────────────────────────────────────┐
│                 credit-resource-bundle              │
│  (资源计费、账单管理、使用量统计、计费策略)           │
├─────────────────────────────────────────────────────┤
│                    credit-bundle                    │
│  (账户管理、交易服务、货币管理、事件系统)             │
└─────────────────────────────────────────────────────┘
```

- **credit-bundle**：提供通用的积分/余额管理基础设施
- **credit-resource-bundle**：基于资源使用的计费业务实现

## 核心概念

### 1. 可计费资源（Billable Resource）
任何可以被计费的资源，包括但不限于：
- 数据库实体（如：用户数、项目数）
- API 调用次数
- 存储空间使用量
- 计算资源使用时长
- 带宽流量

### 2. 资源价格（Resource Price）
定义资源的计费规则：
- 计费周期（按小时/天/月/年）
- 计费方式（按总量/按新增量）
- 价格策略（固定价格/阶梯价格）
- 适用角色（哪些角色需要为此资源付费）

### 3. 资源账单（Resource Bill）
记录资源使用和扣费情况：
- 账单时间
- 资源使用量
- 计费金额
- 账单状态（待支付/已支付/失败）
- 关联的交易记录

### 4. 计费策略（Billing Strategy）
灵活的计费策略支持：
- 固定价格策略
- 阶梯价格策略
- 免费额度策略
- 封顶价格策略

## 架构设计

### 1. 接口层

```php
// 可计费资源接口
interface BillableResourceInterface
{
    public function getResourceType(): string;
    public function getResourceName(): string;
    public function getUsageCount(UserInterface $user, \DateTimeInterface $start, \DateTimeInterface $end): int;
    public function getUsageDetails(UserInterface $user, \DateTimeInterface $start, \DateTimeInterface $end): array;
}

// 计费策略接口
interface BillingStrategyInterface
{
    public function calculate(ResourcePrice $price, int $usage): string;
    public function supports(ResourcePrice $price): bool;
}

// 资源统计接口
interface ResourceUsageProviderInterface
{
    public function supports(string $resourceType): bool;
    public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int;
}
```

### 2. 实体层

```php
// 资源价格实体（增强版）
class ResourcePrice
{
    // 基础信息
    private string $title;
    private string $resourceType;
    private FeeCycle $cycle;
    private string $currency;
    
    // 价格配置
    private string $price;
    private ?string $topPrice;
    private ?string $bottomPrice;
    private ?array $priceRules; // 阶梯价格等复杂规则
    
    // 计费配置
    private ?int $freeQuota; // 免费额度
    private ?string $billingStrategy; // 计费策略类名
    
    // 关联关系
    private Collection $roles; // 适用角色
    private bool $valid;
    
    // 时间配置
    private ?\DateTimeInterface $startTime;
    private ?\DateTimeInterface $endTime;
}

// 资源账单实体
class ResourceBill
{
    private UserInterface $user;
    private ResourcePrice $resourcePrice;
    private Account $account;
    
    // 账单信息
    private \DateTimeInterface $billTime;
    private \DateTimeInterface $periodStart;
    private \DateTimeInterface $periodEnd;
    
    // 使用量和费用
    private int $usage;
    private array $usageDetails;
    private string $unitPrice;
    private string $totalPrice;
    private string $actualPrice; // 实际扣费金额（考虑优惠等）
    
    // 状态管理
    private BillStatus $status;
    private ?Transaction $transaction;
    private ?string $failureReason;
    
    // 审计信息
    private \DateTimeInterface $createTime;
    private ?\DateTimeInterface $paidAt;
}

// 账单状态枚举
enum BillStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
```

### 3. 服务层

```php
// 资源使用统计服务
class ResourceUsageService
{
    private iterable $providers; // ResourceUsageProviderInterface[]
    
    public function getUsage(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): int;
    public function getUsageDetails(UserInterface $user, string $resourceType, \DateTimeInterface $start, \DateTimeInterface $end): array;
    public function getBatchUsage(UserInterface $user, array $resourceTypes, \DateTimeInterface $start, \DateTimeInterface $end): array;
}

// 账单服务
class BillService
{
    private ResourceUsageService $usageService;
    private iterable $strategies; // BillingStrategyInterface[]
    private TransactionService $transactionService;
    
    public function generateBill(UserInterface $user, ResourcePrice $price, \DateTimeInterface $billTime): ResourceBill;
    public function processBill(ResourceBill $bill): void;
    public function queryBills(array $criteria): array;
    public function getBillSummary(UserInterface $user, \DateTimeInterface $start, \DateTimeInterface $end): array;
}

// 计费调度服务
class BillingSchedulerService
{
    public function scheduleHourlyBilling(): void;
    public function scheduleDailyBilling(): void;
    public function scheduleMonthlyBilling(): void;
    public function scheduleYearlyBilling(): void;
}
```

### 4. 策略实现

```php
// 固定价格策略
class FixedPriceStrategy implements BillingStrategyInterface
{
    public function calculate(ResourcePrice $price, int $usage): string
    {
        $total = bcmul($price->getPrice(), (string)$usage, 5);
        
        // 应用封顶价
        if ($price->getTopPrice() && bccomp($total, $price->getTopPrice(), 5) > 0) {
            return $price->getTopPrice();
        }
        
        // 应用保底价
        if ($price->getBottomPrice() && bccomp($total, $price->getBottomPrice(), 5) < 0) {
            return $price->getBottomPrice();
        }
        
        return $total;
    }
}

// 阶梯价格策略
class TieredPriceStrategy implements BillingStrategyInterface
{
    public function calculate(ResourcePrice $price, int $usage): string
    {
        $rules = $price->getPriceRules();
        $total = '0';
        
        foreach ($rules as $tier) {
            $tierUsage = min($usage, $tier['max']) - $tier['min'];
            if ($tierUsage > 0) {
                $tierPrice = bcmul($tier['price'], (string)$tierUsage, 5);
                $total = bcadd($total, $tierPrice, 5);
            }
        }
        
        return $total;
    }
}
```

## 业务流程

### 1. 账单生成流程

```
定时任务触发
    ↓
获取需要计费的资源价格配置
    ↓
根据角色获取需要计费的用户
    ↓
批量获取用户的资源使用量
    ↓
根据计费策略计算费用
    ↓
生成账单记录
    ↓
异步处理账单扣费
```

### 2. 扣费流程

```
账单处理开始
    ↓
验证账单状态
    ↓
获取用户账户
    ↓
调用 TransactionService 扣费
    ↓
更新账单状态
    ↓
发送扣费通知
```

## 扩展点

### 1. 自定义资源统计
通过实现 `ResourceUsageProviderInterface` 支持各种资源的统计：
- 数据库实体统计
- Redis 键值统计
- API 调用统计
- 文件存储统计

### 2. 自定义计费策略
通过实现 `BillingStrategyInterface` 支持复杂的计费规则：
- 按时段差异化计费
- 基于使用量的折扣
- 套餐包计费
- 预付费/后付费切换

### 3. 账单审核流程
支持账单生成后的人工审核：
- 大额账单审核
- 异常使用量审核
- 账单调整和退款

## 数据迁移计划

### 第一阶段：基础结构调整
1. 添加 ResourceBill 实体和仓库
2. ResourcePrice 添加角色关联
3. 创建新的服务类

### 第二阶段：功能迁移
1. 迁移现有的计费逻辑到新服务
2. 保持向后兼容
3. 添加数据迁移脚本

### 第三阶段：功能增强
1. 实现新的计费策略
2. 添加账单查询 API
3. 完善监控和报警

## 监控指标

1. **计费任务监控**
   - 任务执行成功率
   - 任务执行时长
   - 异常任务数量

2. **账单监控**
   - 账单生成数量
   - 扣费成功率
   - 平均账单金额

3. **性能监控**
   - 资源统计查询耗时
   - 账单处理队列长度
   - 数据库查询性能

## 安全考虑

1. **权限控制**
   - 账单查询权限
   - 资源价格配置权限
   - 账单调整权限

2. **数据安全**
   - 账单数据加密存储
   - 敏感操作审计日志
   - 防止重复扣费

3. **并发控制**
   - 账单生成去重
   - 扣费操作加锁
   - 幂等性保证