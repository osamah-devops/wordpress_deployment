# WordPress AWS ECS Deployment

Automated deployment of WordPress to AWS ECS with separated infrastructure and application workflows.

## Quick Start

### 1. Prerequisites
- AWS Account with appropriate IAM permissions
- GitHub repository with Actions enabled
- AWS OIDC provider configured (recommended) or AWS credentials

### 2. Configure AWS Roles
Create two IAM roles in your AWS account and update the role ARNs in both workflow files:
- `github-actions-infra-deploy` - For infrastructure deployment
- `github-actions-ecs-deploy` - For application deployment

See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md#step-1-create-iam-roles) for detailed setup.

### 3. Update Workflow Variables
Edit both workflow files and replace:
- `123456789012` → Your AWS Account ID  
- `us-east-1` → Your AWS region
- Role ARNs with your actual roles

### 4. Deploy Infrastructure
```bash
# Via GitHub CLI
gh workflow run deploy-infrastructure.yaml

# Via GitHub UI
# Go to Actions → Deploy Infrastructure to AWS → Run workflow
```

This creates:
- VPC with 2 AZs for high availability
- Application Load Balancer with HTTPS
- ECS Fargate cluster with auto-scaling
- Secrets Manager for credentials
- CloudWatch logging

### 5. Deploy Application
```bash
# Via GitHub CLI
gh workflow run deploy-wordpress.yaml

# Or push changes to application files (auto-triggers):
# - Dockerfile
# - WordPress config (wp-config.php, php.ini, etc.)
# - wp-content/ directory
```

This builds and deploys the WordPress container:
- Builds Docker image
- Pushes to ECR
- Updates ECS service
- Validates deployment

## Repository Structure

```
├── .github/workflows/
│   ├── deploy-infrastructure.yaml    # Infrastructure provisioning
│   └── deploy-wordpress.yaml          # Application deployment
├── infrastructure/
│   ├── networking.yaml               # VPC, subnets, ALB, security groups
│   ├── secrets.yaml                  # Secrets Manager, IAM roles, logs
│   └── ecs.yaml                      # ECS cluster, service, auto-scaling
├── Dockerfile                        # WordPress container image
├── nginx.conf                        # Nginx configuration
├── php.ini                           # PHP settings
├── uploads.ini                       # PHP upload limits
├── wp-config.php                     # WordPress configuration
├── wp-content/                       # WordPress themes, plugins, uploads
├── DEPLOYMENT_GUIDE.md               # Comprehensive deployment guide
└── README.md                         # This file
```

## Workflow Overview

### Infrastructure Workflow (deploy-infrastructure.yaml)

Runs when:
- Changes pushed to `infrastructure/` directory
- Manual trigger via GitHub UI

Jobs (sequential):
1. **Validate** - CloudFormation syntax check
2. **Deploy Secrets** - Credentials & IAM roles
3. **Deploy Networking** - VPC & networking layer
4. **Deploy ECS** - Compute & orchestration
5. **Validate Deployment** - Health checks

### Application Workflow (deploy-wordpress.yaml)

Runs when:
- Changes pushed to WordPress files (Dockerfile, wp-config.php, etc.)
- Manual trigger via GitHub UI

Jobs (sequential):
1. **Check Infrastructure** - Verifies stacks are ready
2. **Build and Push** - Docker image to ECR
3. **Update Task Definition** - Updates container config
4. **Deploy to ECS** - Updates service, waits for stability
5. **Validate Deployment** - Health checks

## CloudFormation Stacks

| Stack | Description | Dependencies |
|-------|-------------|--------------|
| `wordpress-secrets` | Secrets Manager, IAM roles, logs | None |
| `wordpress-networking` | VPC, subnets, ALB, security groups | wordpress-secrets |
| `wordpress-db` | RDS MySQL, backups, monitoring, alarms | wordpress-networking |
| `wordpress-ecs` | ECS cluster, service, auto-scaling | wordpress-db |

## Key Features

✅ **High Availability**
- 2 Availability Zones
- Auto-scaling (2-4 tasks)
- Application Load Balancer with health checks
- Multi-NAT Gateway redundancy

✅ **Security**
- Secrets Manager for credentials
- Private subnets for ECS tasks
- Security groups with least privilege
- HTTPS enforcement (HTTP redirects)
- IAM roles per service

✅ **Separation of Concerns**
- Infrastructure changes independent of app deployments
- Reusable CloudFormation templates
- Clear dependency ordering
- Infrastructure validation before deployment

✅ **Observability**
- CloudWatch Container Insights
- Centralized logging (/ecs/wordpress)
- ALB access logs
- CloudFormation stack events

## Deployment Requirements

### AWS Resources
- ECR repository (auto-created)
- Secrets Manager secrets
- VPC with subnets and NAT gateways
- Application Load Balancer
- ECS Fargate cluster

### IAM Permissions
Infrastructure deployment needs:
- CloudFormation, VPC, EC2, IAM, Secrets Manager, Logs

Application deployment needs:
- ECR, ECS, Secrets Manager

## Cost Estimate

**Monthly cost (approximate):**
- RDS MySQL (db.t3.micro, 20GB): $15-25
- ALB: $16 + data processing
- NAT Gateways: $32 (2x)
- ECS Fargate: $30-60 (2 tasks)
- Secrets Manager: $0.40
- CloudWatch: $2-5
- **Total: ~$95-140**

See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md#cost-optimization) for cost reduction options.

## Troubleshooting

### Infrastructure deployment failed
```bash
aws cloudformation describe-stack-events \
  --stack-name wordpress-networking
```

### Application deployment failed
```bash
# Check service status
aws ecs describe-services \
  --cluster wordpress-cluster \
  --services wordpress-service

# View logs
aws logs tail /ecs/wordpress --follow
```

### Tasks not running
```bash
# List failed tasks
aws ecs list-tasks \
  --cluster wordpress-cluster \
  --desired-status STOPPED

# Check task details
aws ecs describe-tasks \
  --cluster wordpress-cluster \
  --tasks <task-arn>
```

See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md#troubleshooting) for detailed troubleshooting.

## Commands Reference

```bash
# View all stacks
aws cloudformation list-stacks

# View stack outputs
aws cloudformation describe-stacks \
  --stack-name wordpress-ecs \
  --query 'Stacks[0].Outputs'

# Scale service
aws ecs update-service \
  --cluster wordpress-cluster \
  --service wordpress-service \
  --desired-count 3

# View logs
aws logs tail /ecs/wordpress --follow

# SSH to container (via Session Manager)
aws ecs execute-command \
  --cluster wordpress-cluster \
  --task <task-id> \
  --container wordpress \
  --interactive \
  --command "/bin/bash"
```

## Next Steps

1. **Configure domain**: Update Route 53 to point to ALB
2. **Set up SSL certificate**: ACM certificate for HTTPS
3. **Configure WordPress**: Complete WordPress installation
4. **Backup strategy**: Set up RDS backups and S3 for uploads
5. **Monitoring**: Enable CloudWatch alarms and SNS notifications

## Support

For detailed information, see [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md)

## License

MIT - See LICENSE file for details
