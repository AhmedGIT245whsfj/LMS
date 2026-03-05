output "rds_endpoint" {
  value = aws_db_instance.itverse.address
}

output "s3_bucket_name" {
  value = aws_s3_bucket.itverse.bucket
}

output "vpc_id" {
  value = aws_vpc.itverse.id
}

output "public_subnets" {
  value = [aws_subnet.public_a.id, aws_subnet.public_b.id]
}

output "private_subnets" {
  value = [aws_subnet.private_a.id, aws_subnet.private_b.id]
}
