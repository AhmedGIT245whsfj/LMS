variable "aws_region" {
  type        = string
  description = "AWS region"
  default     = "us-east-1"
}

variable "project_name" {
  type        = string
  description = "Project name prefix for resources"
  default     = "itverse"
}

variable "web_image" {
  type        = string
  description = "Docker image for web app"
  default     = "ahmeduioueu235g/itverse-web:latest"
}

variable "ec2_instance_type" {
  type        = string
  description = "EC2 instance type"
  default     = "t2.micro"
}

variable "db_name" {
  type        = string
  description = "RDS database name"
  default     = "lms_db"
}

variable "db_username" {
  type        = string
  description = "RDS master username"
  default     = "itverse"
}

# IMPORTANT: set this via -var or terraform.tfvars, do NOT hardcode real password in git
variable "db_password" {
  type        = string
  description = "RDS master password"
  sensitive   = true
}

variable "db_instance_class" {
  type        = string
  description = "RDS instance class"
  default     = "db.t3.micro"
}
