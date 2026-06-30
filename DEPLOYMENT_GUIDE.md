# WordPress on AWS ECS - Deployment Guide

## Overview

This repository contains a properly separated GitHub Actions workflow for deploying WordPress to AWS ECS with infrastructure as code. The deployment is split into two distinct workflows:

1. **Infrastructure Deployment** (`deploy-infrastructure.yaml`) - Provisions AWS resources
2. **Application Deployment** (`deploy-wordpress.yaml`) - Builds and deploys the WordPress application

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    AWS Infrastructure                       │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  Route 53 (DNS) ──► ACM (SSL/TLS)                          │
│         ▲                                                     │
│         │                                                     │
│    ┌────┴──────────────────────────────────┐                │
│    │   Application Load Balancer (ALB)      │                │
│    │   - Port 80 (redirect to HTTPS)        │                │
│    │   - Port 443 (HTTPS)                   │                │
│    └────┬───────────────────────────────────┘                │
│         │                                                     │
│    ┌────┴──────────────────────────────────┐                │
│    │   Target Group (IP-based)             │                │
│    │   - Health Checks: /                  │                │
│    └────┬───────────────────────────────────┘                │
│         │                                                     │
│  ┌──────┴──────────────────────────────────────┐            │
│  │  VPC (10.0.0.0/16)                          │            │
│  │                                              │            │
│  │  Public Subnets (ALB + NAT GWs)            │            │
│  │  - Public-AZ1: 10.0.1.0/24                 │            │
│  │  - Public-AZ2: 10.0.2.0/24                 │            │
│  │                                              │            │
│  │  Private Subnets (ECS Tasks)               │            │
│  │  - Private-AZ1: 10.0.11.0/24               │            │
│  │  - Private-AZ2: 10.0.22.0/24               │            │
│  │                                              │            │
│  │  ┌─────────────────────────────────────┐   │            │
│  │  │  ECS Cluster: wordpress-cluster     │   │            │
│  │  │                                       │   │            │
│  │  │  ┌─────────────────────────────────┐ │   │            │
│  │  │  │ ECS Service: wordpress-service │ │   │            │
│  │  │  │ DesiredCount: 2 (auto-scaled)   │ │   │            │
│  │  │  │                                 │ │   │            │
│  │  │  │  ┌──────────────┐              │ │   │            │
│  │  │  │  │ WordPress    │              │ │   │            │
│  │  │  │  │ Container 1  │              │ │   │            │
│  │  │  │  └──────────────┘              │ │   │            │
│  │  │  │                                 │ │   │            │
│  │  │  │  ┌──────────────┐              │ │   │            │
│  │  │  │  │ WordPress    │              │ │   │            │
│  │  │  │  │ Container 2  │              │ │   │            │
│  │  │  │  └──────────────┘              │ │   │            │
│  │  │  └─────────────────────────────────┘ │   │            │
│  │  └─────────────────────────────────────┘   │            │
│  │                                              │            │
│  │  Secrets Manager                           │            │
│  │  - wordpress-db-password                   │            │
│  │  - wordpress-db-user                       │            │
│  └──────────────────────────────────────────┘            │
│                                                               │
│  ECR (Container Registry)                                   │
│  - wordpress-nginx:latest                                   │
│  - wordpress-nginx:<git-sha>                                │
└─────────────────────────────────────────────────────────────┘
```

## Prerequisites

1. **AWS Account** with appropriate permissions
2. **GitHub Repository** with GitHub Actions enabled
3. **AWS Credentials** configured for GitHub Actions (OIDC recommended)
4. **AWS IAM Roles** for GitHub Actions:
   - `github-actions-infra-deploy` (CloudFormation, IAM, Networking)
   - `github-actions-ecs-deploy` (ECR, ECS, Secrets Manager)

## Setup Instructions

### Step 1: Create IAM Roles

Create two IAM roles for GitHub Actions:

#### Infrastructure Deploy Role
```bash
# Trust policy for GitHub Actions
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Federated": "arn:aws:iam::ACCOUNT_ID:oidc-provider/token.actions.githubusercontent.com"
      },
      "Action": "sts:AssumeRoleWithWebIdentity",
      "Condition": {
        "StringEquals": {
          "token.actions.githubusercontent.com:aud": "sts.amazonaws.com",
          "token.actions.githubusercontent.com:sub": "repo:OWNER/REPO:ref:refs/heads/main"
        }
      }
    }
  ]
}

# Attach these policies:
- AWSCloudFormationFullAccess
- IAMFullAccess
- EC2FullAccess
- VPCFullAccess
- ECSFullAccess
- SecretsManagerReadWrite
- ElasticLoadBalancingFullAccess
```

#### Application Deploy Role
```bash
# Trust policy: same as above
# Attach these policies:
- EC2ContainerRegistryFullAccess
- AmazonECS_FullAccess
- AmazonEC2ContainerServiceRoleForServiceAutoscaling
- SecretsManagerReadWrite
```

### Step 2: Update Workflow Configuration

Edit `.github/workflows/deploy-infrastructure.yaml` and `.github/workflows/deploy-wordpress.yaml`:

Replace these values:
- `123456789012` → Your AWS Account ID
- `us-east-1` → Your preferred AWS region
- `arn:aws:iam::123456789012:role/github-actions-infra-deploy` → Your actual role ARN
- `arn:aws:iam::123456789012:role/github-actions-ecs-deploy` → Your actual role ARN

### Step 3: Configure Infrastructure Parameters

Update `infrastructure/secrets.yaml` parameters in your CloudFormation deployment:

```bash
# You'll be prompted for these when deploying
- DBMasterUsername: admin
- DBMasterPassword: (securely generated password)
```

Or pass them via AWS CLI:

```bash
aws cloudformation deploy \
  --template-file infrastructure/secrets.yaml \
  --stack-name wordpress-secrets \
  --parameter-overrides \
    DBMasterUsername=admin \
    DBMasterPassword=YourSecurePassword123! \
  --capabilities CAPABILITY_NAMED_IAM
```

### Step 4: Configure Networking Parameters (if needed)

If your `infrastructure/networking.yaml` requires parameters:

```bash
aws cloudformation deploy \
  --template-file infrastructure/networking.yaml \
  --stack-name wordpress-networking \
  --parameter-overrides \
    HostedZoneId=Z1234567890ABC \
    DomainName=example.com
```

## Workflow Execution

### Manual Infrastructure Deployment

To manually deploy or update infrastructure:

```bash
# Via GitHub UI:
# 1. Go to Actions
# 2. Select "Deploy Infrastructure to AWS"
# 3. Click "Run workflow"
# 4. Select branch (main) and click "Run workflow"

# Via GitHub CLI:
gh workflow run deploy-infrastructure.yaml
```

### Automated Application Deployment

The application deployment runs automatically when changes are pushed to:
- `Dockerfile`
- `nginx.conf`
- `php.ini`
- `uploads.ini`
- `wp-config.php`
- `wp-content/**`
- `.github/workflows/deploy-wordpress.yaml`

Or manually:

```bash
# Via GitHub UI or CLI:
gh workflow run deploy-wordpress.yaml
```

## Workflow Details

### Infrastructure Deployment Workflow

**Triggers:**
- Push to `main` when `infrastructure/**` or workflow file changes
- Manual workflow dispatch

**Jobs (Sequential):**

1. **Validate** - CloudFormation template validation
   - Validates: networking.yaml, secrets.yaml, db.yaml, ecs.yaml
   
2. **Deploy Secrets** (depends on Validate)
   - Creates Secrets Manager secrets for database credentials
   - Creates IAM roles for ECS task execution and application permissions
   - Creates CloudWatch log group
   
3. **Deploy Networking** (depends on Secrets)
   - Provisions VPC with 2 AZs
   - Creates public/private subnets
   - Sets up NAT Gateways for high availability
   - Configures security groups
   - Creates Internet Gateway and routing
   
4. **Deploy Database** (depends on Networking)
   - Creates RDS MySQL instance with Multi-AZ
   - Configures automated backups (7 days retention)
   - Creates database security group
   - Exports database endpoint and credentials
   - Sets up CloudWatch monitoring and alarms
   
5. **Deploy ECS** (depends on Database)
   - Creates ECS cluster
   - Provisions Application Load Balancer
   - Creates target groups with health checks
   - Registers ECS task definition with database configuration
   - Creates ECS service with auto-scaling
   
6. **Validate Deployment** (depends on ECS)
   - Checks ECS cluster status
   - Verifies ALB health
   - Validates VPC configuration
   - Checks database connectivity
   - Generates deployment summary

### Application Deployment Workflow

**Triggers:**
- Push to `main` when application files change
- Manual workflow dispatch
- Automatically checks infrastructure readiness

**Jobs (Sequential):**

1. **Check Infrastructure** - Verifies infrastructure stacks exist and are healthy
   - Ensures all required stacks are in COMPLETE state
   - Exports infrastructure outputs
   
2. **Build and Push** (depends on Check Infrastructure)
   - Checks out code
   - Builds Docker image
   - Creates/verifies ECR repository
   - Pushes image with git SHA and latest tags
   
3. **Update Task Definition** (depends on Build and Push)
   - Downloads current ECS task definition
   - Updates container image reference
   - Injects secrets from Secrets Manager
   - Stores updated task definition as artifact
   
4. **Deploy to ECS** (depends on Infrastructure & Task Definition)
   - Downloads updated task definition
   - Updates ECS service
   - Waits for service stability (all tasks running)
   
5. **Validate Deployment** (depends on Deploy)
   - Checks running task status
   - Verifies service health
   - Checks ALB target health
   - Displays deployment summary

## CloudFormation Stack Hierarchy

```
wordpress-secrets (Secrets Manager + IAM Roles)
    ↓
wordpress-networking (VPC + Security Groups + NAT GWs)
    ↓
wordpress-db (RDS MySQL with Multi-AZ + Backups)
    ↓
wordpress-ecs (ECS Cluster + ALB + Service + Auto Scaling)
```

**Important:** Stacks must be deleted in reverse order (ECS → Database → Networking → Secrets)

## Secrets Management

Database credentials are stored securely in AWS Secrets Manager:

```bash
# Retrieve secrets from CLI
aws secretsmanager get-secret-value \
  --secret-id wordpress-db-password \
  --query SecretString \
  --output text

# Update secrets
aws secretsmanager update-secret \
  --secret-id wordpress-db-password \
  --secret-string '{"username":"admin","password":"NewPassword123!"}'
```

## Troubleshooting

### Infrastructure Deployment Fails

Check CloudFormation events:
```bash
aws cloudformation describe-stack-events \
  --stack-name wordpress-networking \
  --query 'StackEvents[?ResourceStatus==`CREATE_FAILED`]'
```

### Application Deployment Fails

1. **Check infrastructure stacks:**
```bash
aws cloudformation describe-stacks \
  --query 'Stacks[?contains(StackName, `wordpress`)].{Name:StackName,Status:StackStatus}'
```

2. **Check ECS service:**
```bash
aws ecs describe-services \
  --cluster wordpress-cluster \
  --services wordpress-service
```

3. **Check CloudWatch logs:**
```bash
aws logs tail /ecs/wordpress --follow
```

### Tasks Not Running

```bash
# Get task ARNs
aws ecs list-tasks \
  --cluster wordpress-cluster \
  --service-name wordpress-service

# Check task details
aws ecs describe-tasks \
  --cluster wordpress-cluster \
  --tasks <task-arn>

# Check stopped task reasons
aws ecs list-tasks \
  --cluster wordpress-cluster \
  --desired-status STOPPED \
  --query 'taskArns' \
  --output table
```

## Cost Optimization

### Current Setup Costs (Estimated Monthly)

- RDS MySQL (db.t3.micro, 20GB): ~$15-25
- ALB: ~$16 + data processing
- NAT Gateway: ~$32 (2x $16 per AZ)
- ECS Fargate: ~$30-60 (2 tasks @ 0.5 vCPU, 512MB)
- Secrets Manager: ~$0.40
- CloudWatch Logs: ~$2-5
- Total: ~$95-140/month

### Cost Reduction Options

1. **Database**: Use Aurora Serverless (pay-per-invocation) for development
2. **Single NAT Gateway**: Remove one NAT for single-AZ (less redundancy)
3. **EC2 instead of Fargate**: Lower compute costs but requires instance management
4. **Reduce task count**: Set DesiredCount to 1 (affects availability)
5. **Reserved Capacity**: Commit 1-3 years for 30-50% savings

## Security Considerations

✅ **Implemented:**
- Database passwords in Secrets Manager (never in code)
- IAM roles with least privilege
- Security groups with restricted ingress
- ALB with HTTPS enforcement
- Private subnets for ECS tasks
- Container images scanned in ECR

⚠️ **Additional Hardening:**
- Enable VPC Flow Logs
- Use AWS WAF on ALB
- Enable GuardDuty for threat detection
- Implement AWS Systems Manager Session Manager
- Use VPC endpoints for private ECR access
- Enable S3 encryption for WordPress uploads

## Useful Commands

```bash
# List all stacks
aws cloudformation list-stacks \
  --stack-status-filter CREATE_COMPLETE UPDATE_COMPLETE

# View stack outputs
aws cloudformation describe-stacks \
  --stack-name wordpress-ecs \
  --query 'Stacks[0].Outputs'

# Get ALB DNS name
aws elbv2 describe-load-balancers \
  --query 'LoadBalancers[?LoadBalancerName==`wordpress-alb`].DNSName' \
  --output text

# Scale service
aws ecs update-service \
  --cluster wordpress-cluster \
  --service wordpress-service \
  --desired-count 3

# View recent logs
aws logs tail /ecs/wordpress --since 1h --follow
```

## Support & Documentation

- [AWS CloudFormation User Guide](https://docs.aws.amazon.com/cloudformation/)
- [AWS ECS Best Practices](https://docs.aws.amazon.com/AmazonECS/latest/developerguide/)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [AWS Secrets Manager](https://docs.aws.amazon.com/secretsmanager/)
