# Messenger Reliability Implementation

This document outlines the comprehensive solution implemented to prevent EntityManager corruption and ensure reliable message processing.

## 🎯 **Problem Solved**

- **EntityManager corruption** due to failed database transactions
- **Worker processes getting stuck** with closed EntityManager
- **Manual intervention required** to restart workers
- **Production reliability issues** with long-running jobs

## 🛠️ **Solutions Implemented**

### 1. **Prevent EntityManager Corruption**

#### **Transaction-Wrapped Message Handler**
- **File**: `src/MessageHandler/UnsubscribeEmailMessageHandler.php`
- **Key Changes**:
  - Wrapped all database operations in transactions
  - Proper rollback on errors
  - EntityManager clearing on exceptions
  - Specific error handling for different exception types

```php
// Start transaction to prevent EntityManager corruption
$this->em->getConnection()->beginTransaction();

try {
    // ... business logic ...
    $this->em->flush();
    $this->em->getConnection()->commit();
} catch (\Doctrine\DBAL\Exception\DriverException $e) {
    $this->em->getConnection()->rollBack();
    $this->em->clear(); // Detach all entities
    throw $e; // Let Messenger retry
}
```

#### **Data Validation**
- **File**: `src/MessageHandler/UnsubscribeEmailMessageHandler.php`
- **Key Changes**:
  - Message length validation before database operations
  - Automatic truncation with logging
  - Prevents SQL truncation errors

```php
private function validateNotificationMessage(string $message): string
{
    $validatedMessage = substr(trim($message), 0, 255);
    if (strlen($message) > 255) {
        $this->logger->warning('Notification message was truncated', [
            'originalLength' => strlen($message),
            'truncatedLength' => strlen($validatedMessage)
        ]);
    }
    return $validatedMessage;
}
```

### 2. **Operational Safety Net**

#### **Worker Restart Limits**
- **File**: `config/packages/messenger.yaml`
- **Configuration**:
  - Restart after 1000 messages
  - Restart after 1 hour (3600 seconds)
  - Memory limit: 512MB
  - Retry strategy with exponential backoff

```yaml
async:
    dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
    options:
        limit: 1000
        time_limit: 3600
        memory_limit: 512
        retry_strategy:
            max_retries: 3
            delay: 1000
            multiplier: 2
```

#### **Supervisor Monitoring**
- **File**: `supervisor/messenger.conf`
- **Features**:
  - Auto-restart on failure
  - Multiple worker processes
  - Health check integration
  - Proper logging

```ini
[program:messenger-consume]
command=php /var/www/html/bin/console messenger:consume async --time-limit=3600 --memory-limit=512M --limit=1000
autostart=true
autorestart=true
startretries=3
```

#### **Health Check Command**
- **File**: `src/Command/MessengerHealthCheckCommand.php`
- **Features**:
  - Database connection verification
  - Failed message monitoring
  - Integration with Supervisor

```bash
php bin/console messenger:health-check
```

#### **Monitoring Script**
- **File**: `scripts/messenger-monitor.sh`
- **Features**:
  - Cron-based monitoring (every 5 minutes)
  - Automatic worker restart on issues
  - Failed message threshold monitoring
  - Comprehensive logging

```bash
# Add to crontab
*/5 * * * * /path/to/scripts/messenger-monitor.sh
```

### 3. **Database Connection Stability**

#### **Doctrine Configuration**
- **File**: `config/packages/doctrine.yaml`
- **Improvements**:
  - Increased connection timeout (8 hours)
  - Auto-reconnect on connection loss
  - Connection pooling support

```yaml
options:
    1002: "SET SESSION wait_timeout=28800"
    1001: "SET SESSION sql_mode = ''"
    1003: "SET SESSION interactive_timeout=28800"
```

## 🚀 **Deployment Instructions**

### 1. **Update Configuration**
```bash
# Clear cache after configuration changes
php bin/console cache:clear
```

### 2. **Install Supervisor**
```bash
# Install supervisor
sudo apt-get install supervisor

# Copy configuration
sudo cp supervisor/messenger.conf /etc/supervisor/conf.d/

# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
```

### 3. **Setup Monitoring**
```bash
# Make monitoring script executable
chmod +x scripts/messenger-monitor.sh

# Add to crontab
crontab -e
# Add: */5 * * * * /path/to/scripts/messenger-monitor.sh
```

### 4. **Start Workers**
```bash
# Start with new configuration
php bin/console messenger:consume async --time-limit=3600 --memory-limit=512M --limit=1000
```

## 📊 **Monitoring & Maintenance**

### **Health Check Commands**
```bash
# Check worker status
php bin/console messenger:health-check

# View failed messages
php bin/console messenger:failed:show

# Retry failed messages
php bin/console messenger:failed:retry

# Stop workers gracefully
php bin/console messenger:stop-workers
```

### **Log Files**
- **Supervisor logs**: `/var/log/supervisor/messenger-consume.log`
- **Monitoring logs**: `/var/log/messenger-monitor.log`
- **Application logs**: Check your application log configuration

### **Key Metrics to Monitor**
- Worker process count
- Failed message count
- Memory usage
- Database connection status
- Message processing rate

## 🔧 **Troubleshooting**

### **Common Issues**

1. **Workers not starting**
   - Check supervisor status: `sudo supervisorctl status`
   - Check logs: `tail -f /var/log/supervisor/messenger-consume-error.log`

2. **High failed message count**
   - Check application logs for specific errors
   - Verify database connectivity
   - Review message handler logic

3. **Memory issues**
   - Adjust `memory_limit` in messenger configuration
   - Monitor with: `ps aux | grep messenger`

### **Emergency Recovery**
```bash
# Force restart all workers
sudo supervisorctl restart messenger-consume:*

# Clear failed messages (if needed)
php bin/console messenger:failed:remove

# Restart monitoring
sudo supervisorctl restart messenger-health
```

## ✅ **Benefits Achieved**

- **Zero manual intervention** required for EntityManager issues
- **Automatic recovery** from connection problems
- **Proactive monitoring** prevents issues before they occur
- **Production-ready** reliability for long-running jobs
- **Comprehensive logging** for debugging and monitoring
- **Graceful degradation** with proper error handling

## 🔄 **Future Improvements**

- **Metrics collection** (Prometheus/Grafana)
- **Alerting system** integration
- **Load balancing** across multiple workers
- **Message prioritization** system
- **Dead letter queue** for permanently failed messages 