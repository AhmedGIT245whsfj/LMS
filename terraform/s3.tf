resource "aws_s3_bucket" "itverse" {
  bucket = "${var.project_name}-${random_id.s3_suffix.hex}"
  tags   = { Name = "${var.project_name}-bucket" }
}

resource "random_id" "s3_suffix" {
  byte_length = 4
}

resource "aws_s3_bucket_versioning" "itverse" {
  bucket = aws_s3_bucket.itverse.id
  versioning_configuration {
    status = "Enabled"
  }
}

resource "aws_s3_bucket_public_access_block" "itverse" {
  bucket                  = aws_s3_bucket.itverse.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}
